<?php
$data_servizio = $_SESSION['data'];

$sql = "WITH parametri AS (
        SELECT CAST(? AS DATE) AS data_servizio
),

-- 1. Identifichiamo tutti i periodi che includono la data del servizio corrente
tutti_periodi AS (
SELECT
        gp.id_periodo,
        gp.tipo_periodo,
        CASE gp.descrizione_periodo
                WHEN 'periodo_pasquale'      THEN 'pasquale'
                WHEN 'periodo_natalizio'     THEN 'natalizio'
                WHEN 'periodo_estivo'        THEN 'estivo'
                WHEN 'periodo_invernale'     THEN 'invernale'
                WHEN 'periodo_primaverile'   THEN 'primaverile'
                WHEN 'periodo_autunnale'     THEN 'autunnale'
                WHEN 'pot_primav L 90'       THEN 'pot primav L 90'
                ELSE gp.descrizione_periodo
        END AS periodo,

        -- Priorità per la gestione delle festività:
        -- 1 = Esclusivi (Natale e Pasqua annullano i macro-periodi)
        -- 2 = Standard e Addizionali
        CASE gp.descrizione_periodo
                WHEN 'periodo_pasquale'     THEN 1
                WHEN 'periodo_natalizio'    THEN 1
                ELSE 2
        END AS tipo_priorita
        FROM gbgar_periodi gp
        CROSS JOIN parametri x
        WHERE x.data_servizio BETWEEN gp.data_inizio AND gp.data_fine
),

-- 2. Applichiamo il filtro di exclusion per Natale e Pasqua
periodi_filtrati AS (
        SELECT id_periodo, periodo, tipo_periodo
        FROM tutti_periodi
        WHERE 
        (tipo_priorita = 1)
        OR 
        (tipo_priorita = 2 AND NOT EXISTS (SELECT 1 FROM tutti_periodi WHERE tipo_priorita = 1))
        ),

-- 3. Generiamo i periodi validi includendo le scomposizioni storiche estive
periodo_valido AS (
        SELECT id_periodo, periodo, tipo_periodo FROM periodi_filtrati
        WHERE periodo IS NOT NULL
        
        UNION
        
        -- Sotto-periodo Giugno e Settembre
        SELECT id_periodo, 'giu_sett' AS periodo, tipo_periodo
        FROM periodi_filtrati, parametri x
        WHERE MONTH(x.data_servizio) IN (6,9) AND periodo = 'estivo'
        
        UNION
        
        -- Sotto-periodo Luglio e Agosto
        SELECT id_periodo, 'lug_ago' AS periodo, tipo_periodo
        FROM periodi_filtrati, parametri x
        WHERE MONTH(x.data_servizio) IN (7,8) AND periodo = 'estivo'
),

-- 4. Determiniamo quali frequenze dell'ENUM sono attive nel giorno corrente
giorno_frequenze AS (
        SELECT 'giornaliera' AS frequenza
        UNION ALL
        SELECT
                CASE 
                -- Se è Domenica (1) o è presente nella tabella delle festività
                WHEN DAYOFWEEK(x.data_servizio) = 1
                OR EXISTS (SELECT 1 FROM gbgar_festivita f WHERE f.GIORNO = x.data_servizio)
                THEN 'festiva'
                -- Se è Sabato (7)
                WHEN DAYOFWEEK(x.data_servizio) = 7 
                THEN 'sabato'
                -- Da Lunedì (2) a Venerdì (6)
                ELSE 'feriale'       
        END AS frequenza
        FROM parametri x
        UNION ALL
         -- Il Sabato (non festivo) è considerato anche giorno feriale
        SELECT 'feriale' AS frequenza
        FROM parametri x
        WHERE DAYOFWEEK(x.data_servizio) = 7
          AND NOT EXISTS (SELECT 1 FROM gbgar_festivita f WHERE f.GIORNO = x.data_servizio)
        UNION ALL
        -- Gestione della frequenza specifica 'mar-gio-sab'
        SELECT 'mar-gio-sab' AS frequenza
        FROM parametri x 
        WHERE DAYOFWEEK(x.data_servizio) IN (3,5,7) 
        UNION ALL
        -- Gestione della frequenza specifica 'lun-ven'
        SELECT 'lun-ven' AS frequenza
        FROM parametri x
        WHERE DAYOFWEEK(x.data_servizio) BETWEEN 2 AND 6
)

-- 5. Selezione finale unendo le corse, la tabella ponte e i filtri calcolati
SELECT DISTINCT
        c.ID_CORSA AS id_corsa,
        c.LINEA AS linea,
        ic.ubicazione AS partenza,
        DATE_FORMAT(c.ORARIO_CORSA,'%H:%i') AS orario_corsa,
        fc.ubicazione AS arrivo,
        c.FINE_CORSA,
        pv.periodo,
        cc.frequenza AS frequenza_tabella

        FROM gbgar_corse c
        INNER JOIN gbgar_fermate ic         ON c.PARTENZA = ic.id_fermata
        INNER JOIN gbgar_fermate fc         ON c.ARRIVO = fc.id_fermata
        INNER JOIN gbgar_cadenza_corse cc   ON c.ID_CORSA = cc.id_corsa
        INNER JOIN periodo_valido pv        ON cc.id_periodo = pv.id_periodo
        INNER JOIN giorno_frequenze gf       ON cc.frequenza = gf.frequenza
        WHERE
	c.ATTIVA = 1
        -- Esclude le corse già assegnate al servizio nella data odierna
        AND NOT EXISTS (
                SELECT 1 FROM gbgar_serv_master m
                JOIN gbgar_serv_detail d ON m.id_master = d.id_master
                WHERE d.id_corsa = c.ID_CORSA AND m.data = ?
        )
ORDER BY
        c.LINEA, c.ORARIO_CORSA;
";

// Esecuzione tramite Prepared Statement con l'oggetto globale $Conn
$stmt = $Conn->prepare($sql);
if (!$stmt) {
        echo "<div style='color:red; background:#eee; padding:10px; margin:10px; border:1px solid red; font-family:sans-serif;'>";
        echo "<b>Errore preparazione query delle corse:</b> " . $Conn->error;
        echo "</div>";
        $corse_da_svolgere = false;
} else {
        $stmt->bind_param("ss", $data_servizio, $data_servizio);
        $stmt->execute();
        $corse_da_svolgere = $stmt->get_result();
        $stmt->close();
}
?>
