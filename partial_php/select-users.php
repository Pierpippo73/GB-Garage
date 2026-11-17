<?php
$query_sql = "SELECT name FROM gbgar_users where id not in (188,193)";
$lista_autisti = $Conn->query($query_sql);
//controllo sull'esecuzione della query
if ($lista_autisti == FALSE){
die("Errore nell'esecuzione della query:" . $query_sql);
}
?>
