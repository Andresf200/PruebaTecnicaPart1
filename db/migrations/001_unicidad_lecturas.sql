-- Migracion 001: unicidad de lecturas por (contrato, fecha, tipo)
--
-- 1. Si hay duplicados con valores distintos, aborta sin cambiar nada.
-- 2. Si los duplicados son identicos, conserva la primera recibida y copia
--    el resto a dbo.lecturas_depuradas antes de borrarlo.
-- 3. Crea el indice unico que garantiza la idempotencia.
--
-- Es idempotente: si el indice ya existe, no hace nada.

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

USE lecturas;
GO

-- Auditoria de lo eliminado
IF OBJECT_ID('dbo.lecturas_depuradas', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.lecturas_depuradas (
        id               INT           NOT NULL PRIMARY KEY, -- id original en dbo.lecturas
        contrato_id      INT           NOT NULL,
        fecha_lectura    DATE          NOT NULL,
        lectura_kwh      INT           NOT NULL,
        tipo             VARCHAR(10)   NOT NULL,
        fecha_recepcion  DATETIME2(0)  NOT NULL,
        motivo           VARCHAR(100)  NOT NULL,
        fecha_depuracion DATETIME2(0)  NOT NULL
    );
END;
GO

BEGIN TRY
    IF EXISTS (
        SELECT 1 FROM sys.indexes
        WHERE name = 'ux_lecturas_contrato_fecha_tipo'
          AND object_id = OBJECT_ID('dbo.lecturas')
    )
    BEGIN
        PRINT 'Migracion 001 ya aplicada: el indice unico existe.';
    END
    ELSE
    BEGIN
        BEGIN TRANSACTION;

        IF EXISTS (
            SELECT 1
            FROM dbo.lecturas
            GROUP BY contrato_id, fecha_lectura, tipo
            HAVING COUNT(*) > 1 AND COUNT(DISTINCT lectura_kwh) > 1
        )
            THROW 50001, 'Hay lecturas duplicadas con valores distintos: requiere decision de negocio. No se ha modificado nada.', 1;

        ;WITH ordenadas AS (
            SELECT id,
                   ROW_NUMBER() OVER (
                       PARTITION BY contrato_id, fecha_lectura, tipo
                       ORDER BY fecha_recepcion, id
                   ) AS rn
            FROM dbo.lecturas
        )
        DELETE l
        OUTPUT deleted.id, deleted.contrato_id, deleted.fecha_lectura,
               deleted.lectura_kwh, deleted.tipo, deleted.fecha_recepcion,
               'duplicado identico', SYSDATETIME()
        INTO dbo.lecturas_depuradas
             (id, contrato_id, fecha_lectura, lectura_kwh, tipo,
              fecha_recepcion, motivo, fecha_depuracion)
        FROM dbo.lecturas AS l
        INNER JOIN ordenadas AS o ON o.id = l.id
        WHERE o.rn > 1;

        CREATE UNIQUE INDEX ux_lecturas_contrato_fecha_tipo
            ON dbo.lecturas (contrato_id, fecha_lectura, tipo);

        COMMIT TRANSACTION;
        PRINT 'Migracion 001 aplicada.';
    END
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
GO