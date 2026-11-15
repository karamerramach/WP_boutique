<?php
/** Fallback template for ordinary WordPress pages and posts. */
get_header();
?>
<main class="nova-default-content shell" style="padding-top:4rem;padding-bottom:4rem;">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <article <?php post_class(); ?> id="post-<?php the_ID(); ?>">
            <h1><?php the_title(); ?></h1>
            <div class="entry-content"><?php the_content(); ?></div>
        </article>
    <?php endwhile; else : ?>
        <p><?php esc_html_e('Aucun contenu à afficher.', 'nova-boutique'); ?></p>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
