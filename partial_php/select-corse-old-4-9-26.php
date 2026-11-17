
<?php session_start();?>
<!DOCTYPE html>
<html lang="it">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Script per selezione corse</title>
</head>
<body>
<!--Questo script seleziona la corse che devono apparire nella select del form di gestione dei servizi</h3>-->

<?php

$periodi_db = [];

$periodi_db = $_SESSION['periodi'];
$data_servizio = $_SESSION['data'];
//echo $data_servizio. "<br>";


//echo"<pre>";
//print_r ($periodi_db);
//echo"</pre>";


$inizio_invernale = $periodi_db['periodo_invernale']['data_inizio'];
$fine_invernale = $periodi_db['periodo_invernale']['data_fine'];
$inizio_estivo = $periodi_db['periodo_estivo']['data_inizio'];
$fine_estivo = $periodi_db['periodo_estivo']['data_fine'];
$inizio_natale = $periodi_db['periodo_natalizio']['data_inizio'];
$fine_natale = $periodi_db['periodo_natalizio']['data_fine'];
$inizio_pasqua = $periodi_db['periodo_pasquale']['data_inizio'];
$fine_pasqua = $periodi_db['periodo_pasquale']['data_fine'];

//echo $inizio_invernale . "</br>";
//echo $fine_invernale . "</br>";
//echo $inizio_estivo . "</br>";
//echo $fine_estivo . "</br>";
//echo $inizio_natale . "</br>";
//echo $fine_natale . "</br>";
//echo $inizio_pasqua . "</br>";
//echo $fine_pasqua . "</br>";



//DETERMINAZIONE DELLA DATA ORARIO/PERIODO IN VIGORE

if($data_servizio >= $inizio_invernale && $data_servizio<$inizio_natale){
        $orario_in_vigore="invernale";
        //echo $orario_in_vigore;
}elseif($data_servizio>$fine_natale && $data_servizio<$inizio_pasqua){
        $orario_in_vigore="invernale";
        //echo $orario_in_vigore;
}elseif($data_servizio>$fine_pasqua && $data_servizio<=$fine_invernale){
        $orario_in_vigore="invernale";
        //echo $orario_in_vigore;
}elseif($data_servizio>=$inizio_natale && $data_servizio<=$fine_natale){
        $orario_in_vigore="natalizio";
        //echo $orario_in_vigore;
}elseif($data_servizio>=$inizio_pasqua && $data_servizio<=$fine_pasqua){
        $orario_in_vigore="pasquale";
        //echo $orario_in_vigore;
}else{$orario_in_vigore="estivo";
        //echo $orario_in_vigore;
}

//DETERMINAZIONE GIORNO FESTIVO
//estrazione date festive
$query_festivita="SELECT giorno FROM gbgar_festivita;";
$result_hday=$Conn->query($query_festivita);

//creazione matrice con le date dei giorni festivi
$holiday=[];
while($row=$result_hday->fetch_assoc()){
        $holiday[]=$row['giorno'];
        }
//print_r($holiday);

// DETERMINAZIONE GIORNO DELLA SETTIMANA
$dayweek = new DateTime($data_servizio);

//DETERMINAZIONE GIORNI FESTIVI
if(in_array($data_servizio,$holiday) || $dayweek->format('w')==0){
        $giorno_servizio="festivo";
	//echo "<br>". $giorno_servizio;
}elseif($dayweek->format('w')>=1 || $dayweek->format('w')<=5){
        $giorno_servizio="feriale";        
}else{$giorno_servizio="sabato";
        //echo "<br>". $giorno_servizio;
}




/*README
Le condizioni sono impostate affinché la query sulla tabella users deve sempre essere eseguita;
mentre:
Le query sulle tabelle che contengono le linee devono essere eseguite sia che la data di riferimento sia o meno impostata, perchè altrimenti il form si blocca mentre
deve cmq permettere la modifica e la cancellazione delle registrazioni;
Nel caso sia impostata (!empty($data_grezza)) la data sarà in ogni caso compresa tra uno degli intervalli definiti.
*/

//query relativo a periodo invernale, giorno feriale + sabato
if($orario_in_vigore==="invernale" && $giorno_servizio==="feriale" || $giorno_servizio==="sabato"){
        $query_corse_sql =
        "SELECT id_corsa,linea,gbgar_fermate.ubicazione as partenza,DATE_FORMAT(orario_corsa,'%H:%i') AS orario_corsa
        FROM gbgar_corse C
        JOIN gbgar_fermate 
        ON C.partenza=gbgar_fermate.id_fermata
        JOIN gbgar_tab_marcia T
        ON C.id_tm = T.id_tm
        WHERE T.periodo='invernale'
        AND T.frequenza IN('scolastica','giornaliera')
        AND NOT EXISTS(
        SELECT 1 FROM gbgar_serv_master M
        JOIN gbgar_serv_detail D ON M.id_master=D.id_master
        WHERE D.id_corsa=C.id_corsa
        AND M.data='$data_servizio');";
        $corse_da_svolgere = $Conn->query($query_corse_sql);
}//query relativo a periodo natalizio, giorno feriale
if($orario_in_vigore==="natalizio" && $giorno_servizio==="feriale"){
        $query_corse_sql =
        "SELECT id_corsa,linea,gbgar_fermate.ubicazione as partenza,DATE_FORMAT(orario_corsa,'%H:%i') AS orario_corsa
        FROM gbgar_corse C
        JOIN gbgar_fermate
        ON C.partenza=gbgar_fermate.id_fermata
        JOIN gbgar_tab_marcia T
        ON C.id_tm = T.id_tm
        WHERE T.periodo='invernale'
        AND T.frequenza='feriale'
        AND NOT EXISTS(
        SELECT 1 FROM gbgar_serv_master M
        JOIN gbgar_serv_detail D ON M.id_master=D.id_master
        WHERE D.id_corsa=C.id_corsa
        AND M.data='$data_servizio');";
        $corse_da_svolgere = $Conn->query($query_corse_sql);
}//query relativo a periodo natalizio, giorno festivo
if($orario_in_vigore==="natalizio" && $giorno_servizio==="festivo"){
        $query_corse_sql =
        "SELECT id_corsa,linea,gbgar_fermate.ubicazione as partenza,DATE_FORMAT(orario_corsa,'%H:%i') AS orario_corsa
        FROM gbgar_corse C
        JOIN gbgar_fermate
        ON C.partenza=gbgar_fermate.id_fermata
        JOIN gbgar_tab_marcia T
        ON C.id_tm = T.id_tm
        WHERE T.periodo='invernale'
        AND T.frequenza='festiva'
        AND NOT EXISTS(
        SELECT 1 FROM gbgar_serv_master M
        JOIN gbgar_serv_detail D ON M.id_master=D.id_master
        WHERE D.id_corsa=C.id_corsa
        AND M.data='$data_servizio');";
        $corse_da_svolgere = $Conn->query($query_corse_sql);
}//query relativo a periodo pasquale, giorno feriale
if($orario_in_vigore==="pasquale" && $giorno_servizio==="feriale"){
        $query_corse_sql =
        "SELECT id_corsa,linea,gbgar_fermate.ubicazione as partenza,DATE_FORMAT(orario_corsa,'%H:%i') AS orario_corsa
        FROM gbgar_corse C
        JOIN gbgar_fermate
        ON C.partenza=gbgar_fermate.id_fermata
        JOIN gbgar_tab_marcia T
        ON C.id_tm = T.id_tm
        WHERE T.periodo='invernale'
        AND T.frequenza='feriale'
        AND NOT EXISTS(
        SELECT 1 FROM gbgar_serv_master M
        JOIN gbgar_serv_detail D ON M.id_master=D.id_master
        WHERE D.id_corsa=C.id_corsa
        AND M.data='$data_servizio');";
        $corse_da_svolgere = $Conn->query($query_corse_sql);
}//query relativo a periodo estivo, giorno feriale+sabato
if($orario_in_vigore==="estivo" && $giorno_servizio==="feriale"|| $giorno_servizio==="sabato"){
        $query_linee_sql = 
        "SELECT id_corsa,linea,gbgar_fermate.ubicazione as partenza,DATE_FORMAT(orario_corsa,'%H:%i') AS orario_corsa 
        FROM gbgar_corse C
        JOIN gbgar_fermate
        ON C.partenza=gbgar_fermate.id_fermata
        JOIN gbgar_tab_marcia T
        ON C.id_tm = T.id_tm
	WHERE T.periodo='estivo'
	AND T.frequenza='feriale'
        AND NOT EXISTS(
        SELECT 1 FROM gbgar_serv_master M
        JOIN gbgar_serv_detail D ON M.id_master=D.id_master
        WHERE D.id_corsa = C.id_corsa
        AND M.data='$data_servizio');";
	$corse_da_svolgere = $Conn->query($query_linee_sql);
}//query relativa al periodo estivo giorno festivo
if($orario_in_vigore==="estivo" && $giorno_servizio==="festivo"){
        $query_linee_sql = 
        "SELECT id_corsa,linea,gbgar_fermate.ubicazione as partenza,DATE_FORMAT(orario_corsa,'%H:%i') AS orario_corsa 
        FROM gbgar_corse C
        JOIN gbgar_fermate
        ON C.partenza=gbgar_fermate.id_fermata
        JOIN gbgar_tab_marcia T
        ON C.id_tm = T.id_tm
        WHERE T.periodo='estivo'
        AND T.frequenza='festiva'
        AND NOT EXISTS(
        SELECT 1 FROM gbgar_serv_master M
        JOIN gbgar_serv_detail D ON M.id_master=D.id_master
        WHERE D.id_corsa = C.id_corsa
        AND M.data='$data_servizio');";
        $corse_da_svolgere = $Conn->query($query_linee_sql);	
}elseif($corse_da_svolgere == FALSE){
        die("Errore nell'esecuzione della query:" . $query_linee_sql);
        }
?>

</body>
</html>
