<?php
get_header();
?>

<main class="post-single">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <article <?php post_class(); ?>>
                <div class="post-hero">
                    <?php if (has_post_thumbnail()) : ?>
                        <?php the_post_thumbnail('large'); ?>
                    <?php endif; ?>
                    <h1><?php the_title(); ?></h1>
                </div>
                <p class="post-meta">
                    <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time>
                    <span class="post-author"><?php echo esc_html(get_the_author()); ?></span>
                </p>
                <div class="post-content">
                    <?php the_content(); ?>
                </div>
                <nav class="post-navigation" aria-label="Post navigation">
                    <?php previous_post_link('%link', '&larr; %title'); ?>
                    <?php next_post_link('%link', '%title &rarr;'); ?>
                </nav>
            </article>
        <?php endwhile; ?>
    <?php endif; ?>
</main>

<?php get_template_part('template-parts/comments-box', null, ['initially_open' => true]); ?>

<?php
get_footer();
