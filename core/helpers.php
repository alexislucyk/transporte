<?php
/**
 * Funciones de ayuda globales para Trans Cargo Hub
 */

function formatMoney($amount) {
    return '$' . number_format($amount, 2, ',', '.');
}

function formatDate($date) {
    if (!$date) return '-';
    return date('d/m/Y', strtotime($date));
}