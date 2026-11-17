<?php
$formatter = new IntlDateFormatter(
    'it_IT',
    IntlDateFormatter::FULL, // Giorno della settimana esteso
    IntlDateFormatter::NONE, // Nessuna ora
    'Europe/Rome',
    IntlDateFormatter::GREGORIAN,
    'EEEE d MMMM Y' // Formato personalizzato: Giovedì 19 Febbraio 2026
);
?>
