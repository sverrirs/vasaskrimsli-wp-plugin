<?php
/**
 * Bókaskrá feature: the Verslun page's catalogue of books and posters, one block per series and one for them all.
 * No database: the catalogue is data/baekur.json and the pictures in assets/, made by migration/baekur.py.
 */

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/includes/Verslun.php';
require __DIR__ . '/includes/Block.php';

add_action( 'init', array( Vasaskrimsli\Baekur\Block::class, 'register' ) );
