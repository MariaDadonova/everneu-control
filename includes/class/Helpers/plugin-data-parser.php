<?php
// A utility for extracting plug-in data from the text of its main file

if (!function_exists('get_file_data_from_text')) {
    function get_file_data_from_text($text, $default_headers) {
        $headers = [];
        foreach ($default_headers as $field => $regex_name) {
            preg_match('/^' . preg_quote($regex_name, '/') . ':\s*(.+)$/mi', $text, $matches);
            $headers[$field] = $matches[1] ?? '';
        }
        return $headers;
    }
}