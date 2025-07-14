<?php
defined('ABSPATH') || exit;

class WordClever_Loader {
    public static function init() {
        self::load_classes();
        self::add_hooks();
    }

    private static function load_classes() {
        require_once WORDCLEVER_PATH . 'includes/class-wordclever-metabox.php';
        require_once WORDCLEVER_PATH . 'includes/class-wordclever-api-handler.php';
    }

    private static function add_hooks() {
        // Initialize the metabox functionality
        WordClever_MetaBox::init();
    }
}
