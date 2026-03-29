<?php

/**
 * Theme Settings admin page for the Dracka theme.
 *
 * Registers an Appearance > Theme Settings page where the admin can
 * manage a pool of custom 404 images. One image from the pool is
 * displayed at random on every 404 response.
 */

if (! defined('ABSPATH')) {
    exit;
}

// -----------------------------------------------------------------------------
// Option registration
// -----------------------------------------------------------------------------

/**
 * Sanitizes the submitted 404 image ID array.
 *
 * Coerces each value to a positive integer and discards anything that
 * is not a valid WordPress attachment.
 *
 * @param mixed $raw Raw value from the settings form.
 * @return int[]
 */
function dracka_sanitize_404_image_ids($raw)
{
    if (! is_array($raw)) {
        return [];
    }

    $ids = [];
    foreach ($raw as $value) {
        $id = absint($value);
        if ($id > 0 && get_post_type($id) === 'attachment') {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

add_action('admin_init', function () {
    register_setting(
        'dracka_theme_settings',
        'dracka_404_image_ids',
        [
            'sanitize_callback' => 'dracka_sanitize_404_image_ids',
            'default'           => [],
        ]
    );
});

// -----------------------------------------------------------------------------
// Admin menu
// -----------------------------------------------------------------------------

add_action('admin_menu', function () {
    add_theme_page(
        __('Theme Settings', 'dracka'),
        __('Theme Settings', 'dracka'),
        'manage_options',
        'dracka-theme-settings',
        'dracka_theme_settings_page'
    );
});

// -----------------------------------------------------------------------------
// Enqueue scripts – only on the Theme Settings admin page
// -----------------------------------------------------------------------------

add_action('admin_enqueue_scripts', function ($hook_suffix) {
    if ($hook_suffix !== 'appearance_page_dracka-theme-settings') {
        return;
    }

    wp_enqueue_media();

    // Build existing-images data for the JS initializer.
    $ids      = (array) get_option('dracka_404_image_ids', []);
    $existing = [];
    foreach ($ids as $id) {
        $id = absint($id);
        if ($id <= 0) {
            continue;
        }
        $thumb = wp_get_attachment_image_url($id, 'thumbnail');
        $alt   = (string) get_post_meta($id, '_wp_attachment_image_alt', true);
        if ($thumb) {
            $existing[] = [
                'id'  => $id,
                'url' => $thumb,
                'alt' => $alt,
            ];
        }
    }

    $existing_json = wp_json_encode($existing);

    $inline = <<<JS
(function () {
    'use strict';

    var images = {$existing_json};
    var frame;

    function renderGallery() {
        var grid = document.getElementById('dracka-404-gallery');
        if (!grid) { return; }

        grid.innerHTML = '';

        if (images.length === 0) {
            var empty = document.createElement('p');
            empty.className = 'dracka-404-empty';
            empty.textContent = 'No images set. Click "Add Images" to upload or select images.';
            grid.appendChild(empty);
            return;
        }

        images.forEach(function (img, index) {
            var item = document.createElement('div');
            item.className = 'dracka-404-item';

            var thumb = document.createElement('img');
            thumb.src = img.url;
            thumb.alt = img.alt || '';

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'dracka-404-remove';
            remove.setAttribute('aria-label', 'Remove image');
            remove.textContent = '\u2715';

            remove.addEventListener('click', (function (i) {
                return function () {
                    images.splice(i, 1);
                    renderGallery();
                };
            }(index)));

            item.appendChild(thumb);
            item.appendChild(remove);
            grid.appendChild(item);
        });
    }

    function openMediaFrame() {
        if (frame) {
            frame.open();
            return;
        }

        frame = wp.media({
            title   : 'Select 404 Images',
            button  : { text: 'Use these images' },
            library : { type: 'image' },
            multiple: true
        });

        frame.on('select', function () {
            var selection  = frame.state().get('selection');
            var currentIds = images.map(function (i) { return i.id; });

            selection.each(function (attachment) {
                var id = attachment.get('id');
                if (currentIds.indexOf(id) !== -1) { return; }

                var sizes = attachment.get('sizes') || {};
                var url   = (sizes.thumbnail && sizes.thumbnail.url) || attachment.get('url') || '';
                var alt   = attachment.get('alt') || '';

                images.push({ id: id, url: url, alt: alt });
            });

            renderGallery();
        });

        frame.open();
    }

    function writeHiddenInputs() {
        var form = document.getElementById('dracka-theme-settings-form');
        if (!form) { return; }

        var old = form.querySelectorAll('input[name="dracka_404_image_ids[]"]');
        old.forEach(function (el) { el.parentNode.removeChild(el); });

        images.forEach(function (img) {
            var input  = document.createElement('input');
            input.type  = 'hidden';
            input.name  = 'dracka_404_image_ids[]';
            input.value = img.id;
            form.appendChild(input);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        renderGallery();

        var addBtn = document.getElementById('dracka-404-add');
        if (addBtn) {
            addBtn.addEventListener('click', openMediaFrame);
        }

        var form = document.getElementById('dracka-theme-settings-form');
        if (form) {
            form.addEventListener('submit', writeHiddenInputs);
        }
    });
}());
JS;

    wp_add_inline_script('media-editor', $inline);
});

// -----------------------------------------------------------------------------
// Page renderer
// -----------------------------------------------------------------------------

/**
 * Renders the Theme Settings admin page.
 *
 * @return void
 */
function dracka_theme_settings_page()
{
    if (! current_user_can('manage_options')) {
        return;
    }
?>
    <div class="wrap">
        <h1><?php esc_html_e('Theme Settings', 'dracka'); ?></h1>

        <?php settings_errors('dracka_theme_settings'); ?>

        <form method="post" action="options.php" id="dracka-theme-settings-form">
            <?php settings_fields('dracka_theme_settings'); ?>

            <h2><?php esc_html_e('404 Images', 'dracka'); ?></h2>
            <p class="description">
                <?php esc_html_e('Upload or select images to display on 404 error pages. One image is chosen at random each time a 404 occurs. Each image links back to the home page.', 'dracka'); ?>
            </p>

            <style>
                #dracka-404-gallery {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 12px;
                    margin: 16px 0;
                    min-height: 60px;
                    align-items: flex-start;
                }

                .dracka-404-item {
                    position: relative;
                    width: 100px;
                    height: 100px;
                    border: 1px solid #c3c4c7;
                    border-radius: 2px;
                    overflow: hidden;
                }

                .dracka-404-item img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                    display: block;
                }

                .dracka-404-remove {
                    position: absolute;
                    top: 3px;
                    right: 3px;
                    background: rgba(0, 0, 0, 0.6);
                    color: #fff;
                    border: none;
                    border-radius: 2px;
                    width: 20px;
                    height: 20px;
                    font-size: 11px;
                    line-height: 1;
                    cursor: pointer;
                    padding: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .dracka-404-remove:hover {
                    background: rgba(204, 0, 0, 0.85);
                }

                .dracka-404-empty {
                    color: #787c82;
                    margin: 0;
                    align-self: center;
                }
            </style>

            <div id="dracka-404-gallery"></div>

            <button type="button" id="dracka-404-add" class="button">
                <?php esc_html_e('Add Images', 'dracka'); ?>
            </button>

            <?php submit_button(__('Save Settings', 'dracka')); ?>
        </form>
    </div>
<?php
}
