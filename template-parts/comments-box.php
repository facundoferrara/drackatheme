<?php

/**
 * Template Part: Comments Box
 *
 * Collapsible WordPress comments section, reusing .dracka-collapsible structure.
 * Pass ['initially_open' => false] to start collapsed (default: open).
 *
 * @package Dracka
 * @var array $args
 */

$initially_open = isset($args['initially_open']) ? (bool) $args['initially_open'] : true;

$comment_count          = (int) get_comments_number();
$should_start_expanded = $initially_open || $comment_count === 0;
$section_id             = 'comments-' . get_the_ID();
$title_text             = sprintf(
    _n('%s comment', '%s comments', $comment_count, 'dracka'),
    number_format_i18n($comment_count)
);
$comments               = get_comments([
    'post_id' => get_the_ID(),
    'status'  => 'approve',
    'order'   => 'ASC',
]);

$commenter = wp_get_current_commenter();
?>

<section
    class="dracka-collapsible dracka-comments-box"
    data-comments-collapsible>

    <button
        type="button"
        class="dracka-collapsible__toggle"
        aria-expanded="<?php echo $should_start_expanded ? 'true' : 'false'; ?>"
        aria-controls="<?php echo esc_attr($section_id); ?>">
        <span class="dracka-collapsible__arrow" aria-hidden="true"></span>
        <span class="dracka-collapsible__title"><?php echo esc_html($title_text); ?></span>
    </button>

    <div
        id="<?php echo esc_attr($section_id); ?>"
        class="dracka-collapsible__content<?php echo $should_start_expanded ? ' is-open' : ''; ?>"
        <?php if ($should_start_expanded) : ?>style="max-height: none; opacity: 1;" <?php endif; ?>>

        <?php if (!empty($comments)) : ?>
            <ol class="comment-list">
                <?php
                wp_list_comments([
                    'style'      => 'ol',
                    'short_ping' => true,
                ], $comments);
                ?>
            </ol>
        <?php endif; ?>

        <?php
        comment_form([
            'fields' => [
                'author' => sprintf(
                    '<p class="comment-form-author"><label for="author">%s <span class="required" aria-hidden="true">*</span></label><input id="author" name="author" type="text" autocomplete="name" value="%s" size="30" maxlength="245" required></p>',
                    esc_html__('Name', 'dracka'),
                    esc_attr($commenter['comment_author'])
                ),
                'email'  => sprintf(
                    '<p class="comment-form-email"><label for="email">%s <span class="required" aria-hidden="true">*</span></label><input id="email" name="email" type="email" autocomplete="email" value="%s" size="30" maxlength="100" required></p>',
                    esc_html__('Email', 'dracka'),
                    esc_attr($commenter['comment_author_email'])
                ),
            ],
            'comment_notes_before' => '',
            'comment_notes_after'  => '',
            'title_reply'          => esc_html__('Leave a Comment', 'dracka'),
            'label_submit'         => esc_html__('Post Comment', 'dracka'),
        ]);
        ?>

    </div>

</section>