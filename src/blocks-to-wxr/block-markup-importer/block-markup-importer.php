<?php
/**
 * Plugin Name: Blocks to WordPress
 */

define("EXTENSION", ".blockhtml");
define("INDEX_FILE_NAME", "01-index");
define("INDEX_FILE", INDEX_FILE_NAME . EXTENSION);

function import_blocks_markup_from_directory($block_markup_path) {
	$files = get_block_markup_files_to_import($block_markup_path);
	$admin_id = get_admin_id();
	create_pages($files, $admin_id);
}

function get_block_markup_files_to_import($dir) {
    $files = array();
    function scan_directory($dir) {
        $files = array();

        if (is_dir($dir)) {
            $dh = opendir($dir);
            while (($file = readdir($dh)) !== false) {
                if ($file != "." && $file != "..") {
                    $filePath = $dir . '/' . $file;
                    if (is_dir($filePath)) {
                        $nestedFiles = scan_directory($filePath);
                        $files = array_merge($files, $nestedFiles);
                    } elseif (str_ends_with(strtolower($file), EXTENSION)) {
                        $files[] = array(
                            'path' => $filePath,
                            'name' => $file,
                            'content' => file_get_contents($filePath),
                        );
                    }
                }
            }
            closedir($dh);
        }

        return $files;
    }

    $files = scan_directory($dir);
	return $files;
}

function get_admin_id() {
	$admins = get_users(array(
		'role' => 'administrator',
		'orderby' => 'ID',
		'order' => 'ASC',
		'number' => 1
	));

	// Check if there is at least one admin
	if (!empty($admins)) {
		return $admins[0]->ID;
	}
}

function create_pages($pages, $author_id)
{
    $by_path = [];
    foreach($pages as $page) {
        $by_path[$page['path']] = $page;
    }
    sortByKeyLengthAndReadme($by_path);

    $ids_by_path = [];
    foreach($by_path as $page) {
        if(str_ends_with($page['path'], '/' . INDEX_FILE)) {
            $parent_path = dirname(dirname($page['path'])) . '/' . INDEX_FILE;
        } else {
            $parent_path = dirname($page['path']) . '/' . INDEX_FILE;
        }
        if (isset($ids_by_path[$parent_path])) {
            $parent_id = $ids_by_path[$parent_path];
        } else {
            $parent_id = null;
        }
        $ids_by_path[$page['path']] = create_page($page, $parent_id, $author_id);
    }
    return $ids_by_path;
}

function create_page($page, $parent_id=null, $author_id) {
    if (str_ends_with($page['path'], INDEX_FILE)) {
        $page['name'] = basename(dirname($page['path']));
    }

	$post_id = wp_insert_post(array(
		'post_title' => $page['name'],
		'post_content' => $page['content'],
		'post_status' => 'publish',
		'post_type' => 'page',
		'post_parent' => $parent_id,
		'post_author' => $author_id
	));
    
    if (is_wp_error($post_id)) {
        // echo('Failed to insert page: ' . $post_id->get_error_message());
        exit(1);
    }

	return $post_id;
}

function sortByKeyLengthAndReadme(&$array) {
    // Step 1: Extract the keys and sort them
    $keys = array_keys($array);

    usort($keys, function($a, $b) {
        // Bubble the index file to the top within the same directory
        if (basename($a) === INDEX_FILE) return -1;
        if (basename($b) === INDEX_FILE) return 1;
        
        // Sort by key length
        return strlen($a) <=> strlen($b);
    });

    // Step 2: Re-create the array with sorted keys
    $sorted = [];
    foreach ($keys as $key) {
        $sorted[$key] = $array[$key];
    }

    $array = $sorted;
}
