<?php session_start();?>
<!DOCTYPE html>
<html lang="it">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Variabili periodi e feste per output di controllo</title>
</head>

<body>

<?php
//Questo file determina delle variabili che nel file di gestione dei servizi aiuta ad avere un output di controllo sulle date impostate e relativi orari in vigore.
//è incluso quindi nell'articolo "gestione_servizi".

$periodi_db = $_SESSION['periodi'];
$data_servizio = $_SESSION['data'];
//echo $data_servizio ."<br>";



//echo"<pre>";
//print_r ($periodi_db);
//echo"</pre>";


$inizio_invernale = $periodi_db['inverno']['data_inizio'];
$fine_invernale = $periodi_db['inverno']['data_fine'];
$inizio_estivo = $periodi_db['estate']['data_inizio'];
$fine_estivo = $periodi_db['estate']['data_fine'];
$inizio_natale = $periodi_db['natale']['data_inizio'];
$fine_natale = $periodi_db['natale']['data_fine'];
$inizio_pasqua = $periodi_db['pasqua']['data_inizio'];
$fine_pasqua = $periodi_db['pasqua']['data_fine'];

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
$dayweek = ($data_servizio !== null) ? new DateTime($data_servizio) : null ;


//DETERMINAZIONE GIORNI FESTIVI
if ($dayweek instanceof DateTime){
	if(in_array($data_servizio,$holiday) || $dayweek->format('w')==0){
        $giorno_servizio="festivo";
        //echo "<br>". $giorno_servizio;
	}elseif($dayweek->format('w')>=1 && $dayweek->format('w')<6){
        $giorno_servizio="feriale";
        //echo "<br>". $giorno_servizio;
	}elseif($dayweek->format('w')==6){
        $giorno_servizio="sabato";
        //echo"<br>". $giorno_servizio;
	}
}else{$giorno_servizio = null;}
?>


