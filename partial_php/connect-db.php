<?php
try {
$Conn = new mysqli("localhost","root","sprangasql","gbgarage");
}
catch(Exception $e){
//controllo connessione al db.
$error = $e ->getMessage();
echo $error;
};
?>
