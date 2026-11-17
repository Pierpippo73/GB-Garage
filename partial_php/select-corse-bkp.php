<?php session_start();?>
<!DOCTYPE html>
<html lang="it">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Script per selezione corse</title>
</head>
<body>
<!-- Questo script sostituisce il file precedente selezionando le corse usando una struttara sql
tale da sostituire e alleggerire il codice php-->

<?php
//serve solo questa variabile php, il resto lo fa totalmente sql.

$data_servizio = $_SESSION['data'];

$sql="WITH parametri as (
        SELECT CAST(? AS DATE) AS data_servizio
        ),

        -- Mappatura dinamica di tutte i periodi validi per il giorno di servizio

        periodo AS (
        SELECT
        descrizione_periodo,
        CASE descrizione_periodo
                WHEN 'periodo_pasquale'   THEN 'pasquale'
                WHEN 'periodo_natalizio'  THEN 'natalizio'
                WHEN 'periodo_estivo'     THEN 'estivo'
                WHEN 'periodo_invernale'  THEN 'invernale'
        END AS periodo
        FROM gbgar_periodi gp
        CROSS JOIN parametri x
        WHERE x.data_servizio BETWEEN gp.data_inizio AND gp.data_fine
        ORDER BY
        CASE descrizione_periodo
                WHEN 'periodo_pasquale'   THEN 1
                WHEN 'periodo_natalizio'  THEN 2
                WHEN 'periodo_estivo'     THEN 3
                WHEN 'periodo_invernale'  THEN 4
                ELSE 99
        END
        LIMIT 1
        ),

        -- Mappatura dinamica di tutte le frequenze valide per il giorno di servizio

        giorno_frequenze AS (

        SELECT 'giornaliera' AS frequenza -- Valida sempre, per qualsiasi giorno

        UNION ALL

        SELECT
        CASE 
        -- SE È FESTIVO (Domenica o Tabella Festività)
                WHEN DAYOFWEEK(x.data_servizio) = 1
                OR EXISTS (SELECT 1 FROM gbgar_festivita f WHERE f.GIORNO = x.data_servizio)
                THEN 'festiva'
        
        -- SE È SABATO
                WHEN DAYOFWEEK(x.data_servizio) = 7 
                THEN 'sabato'
        
        -- SE È LUNEDÌ-VENERDÌ
                ELSE 'feriale'       
        END
        AS frequenza
        FROM parametri x
        
        UNION ALL
        -- Aggiungiamo le frequenze specifiche richieste per il periodo invernale scolastico
        SELECT 'scolastica' AS frequenza
        FROM parametri x
        WHERE DAYOFWEEK(x.data_servizio) BETWEEN 2 AND 7

        UNION ALL
        SELECT 'sab-scol' AS frequenza
        FROM parametri x 
        WHERE DAYOFWEEK(x.data_servizio) = 7

        UNION ALL
        SELECT 'lun-ven-scol' AS frequenza
        FROM parametri x
        WHERE DAYOFWEEK(x.data_servizio) BETWEEN 2 AND 6

        UNION ALL
        SELECT 'sab-scol' AS frequenza
        FROM parametri x
        WHERE DAYOFWEEK(x.data_servizio) BETWEEN 2 AND 6
        )


        SELECT
        c.id_corsa,
        tm.id_tm,
        c.linea,
        ic.ubicazione AS partenza,
        DATE_FORMAT(c.orario_corsa,'%H:%i')AS orario_corsa,
        fc.ubicazione AS arrivo,
        c.fine_corsa,
        c.km,
        p.periodo,
        tm.frequenza AS frequenza_tabella

        FROM gbgar_corse c
        INNER JOIN gbgar_fermate ic   ON c.partenza = ic.id_fermata
        INNER JOIN gbgar_fermate fc   ON c.ARRIVO = fc.id_fermata
        INNER JOIN gbgar_tab_marcia tm ON tm.id_tm = c.id_tm    
        CROSS JOIN periodo p
        INNER JOIN giorno_frequenze gf ON tm.frequenza = gf.frequenza
        WHERE
        tm.PERIODO = p.periodo
        AND NOT EXISTS(
        SELECT 1 FROM gbgar_serv_master m
        JOIN gbgar_serv_detail d ON m.id_master=d.id_master
        WHERE d.id_corsa=c.id_corsa
        AND m.data=?)
        ORDER BY
        c.orario_corsa;
";

$stmt = $Conn->prepare($sql);

if (!$stmt) {
        die("Errore prepare: " . $Conn->error);
}

$stmt->bind_param("ss", $data_servizio, $data_servizio);

$stmt->execute();

$corse_da_svolgere = $stmt->get_result();

$stmt->close();


?>
</body>
</html>

