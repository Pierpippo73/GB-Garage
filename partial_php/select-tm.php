<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estrazione tabelle di marcia</title>
</head>
<body>
    <?php
        $query_sel_tm = "SELECT * FROM gbgar_tab_marcia ORDER BY tabella ASC;";
        $ris_tm = $Conn->query($query_sel_tm);
        if ($ris_tm == FALSE){
            die("Errore nell'esecuzione della query:" . $query_sel_tm);
        }

        $query_sel_partenze ="SELECT * FROM gbgar_fermate WHERE tipo='capolinea' ORDER BY ubicazione ASC;";
        $ris_partenze = $Conn->query($query_sel_partenze);
        if($ris_partenze==FALSE){
            die("Errore nell'esecuzione della query:" .$query_sel_partenze);
        }

        $query_sel_arrivi ="SELECT * FROM gbgar_fermate WHERE tipo='capolinea' ORDER BY ubicazione ASC;";
        $ris_arrivi = $Conn->query($query_sel_arrivi);
        if($ris_arrivi==FALSE){
            die("Errore nell'esecuzione della query:" .$query_sel_arrivi);
        }
    ?>
</body>
</html>