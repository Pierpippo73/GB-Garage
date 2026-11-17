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
$data_servizio = $_SESSION['data'];

$sql="WITH parametri as (
        SELECT CAST(? AS DATE) AS data_servizio
        ),

        -- 1. Identifichiamo il periodo macro (base) attivo nel database per quel giorno
        periodo_base AS (
        SELECT
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

        -- 2. Output dei periodi attivi: unisce l'estivo generico con i tuoi nuovi sotto-periodi
        periodo AS (
        -- Mantiene il periodo principale (es. 'estivo')
        SELECT periodo FROM periodo_base
        
        UNION
        
        -- SOTTO-PERIODO GIUGNO E SETTEMBRE: 
        -- Se siamo nel macro-periodo estivo E il mese è Giugno (6), oppure settembre (9) si  aggiunge 'giu_sett_est'
        SELECT 'giu_sett' AS periodo
        FROM parametri x
        WHERE MONTH(x.data_servizio) IN (6,9)
        AND EXISTS (SELECT 1 FROM periodo_base WHERE periodo = 'estivo')
        
        UNION
        
        -- SOTTO-PERIODO LUGLIO E AGOSTO:
        -- Se siamo nel macro-periodo estivo E il mese è Settembre (9), aggiunge 'lug_ago_est'
        SELECT 'lug_ago' AS periodo
        FROM parametri x
        WHERE MONTH(x.data_servizio) IN (7,8)
        AND EXISTS (SELECT 1 FROM periodo_base WHERE periodo = 'estivo')
        ),

        -- Mappatura dinamica di tutte le frequenze valide per il giorno di servizio
        giorno_frequenze AS (
        SELECT 'giornaliera' AS frequenza
        UNION ALL
        SELECT
        CASE 
                WHEN DAYOFWEEK(x.data_servizio) = 1
                OR EXISTS (SELECT 1 FROM gbgar_festivita f WHERE f.GIORNO = x.data_servizio)
                THEN 'festiva'
                WHEN DAYOFWEEK(x.data_servizio) = 7 
                THEN 'sabato'
                ELSE 'feriale'       
        END AS frequenza
        FROM parametri x
        UNION ALL
        SELECT 'scolastica' AS frequenza
        FROM parametri x
        WHERE DAYOFWEEK(x.data_servizio) BETWEEN 2 AND 7
        UNION ALL
        SELECT 'mar_gio_sab' AS frequenza
        FROM parametri x 
        WHERE DAYOFWEEK(x.data_servizio) IN (3,5,7) 
        UNION ALL
        SELECT 'lun-ven-scol' AS frequenza
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
        c.linea, c.orario_corsa;
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