<?php
get_header();
?>

<main>
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <article <?php post_class(); ?>>
                <?php if (!is_front_page()) : ?>
                    <h1><?php the_title(); ?></h1>
                <?php endif; ?>
                <div class="entry-content">
                    <?php the_content(); ?>
                </div>
            </article>
        <?php endwhile; ?>
    <?php else : ?>
        <h1>Dracka Comics</h1>
    <?php endif; ?>
</main>

<?php
get_footer();
