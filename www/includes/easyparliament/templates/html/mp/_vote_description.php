<li>
    <?= $description ?>
    <?php if ( $show_link ) { ?>
        <a class="vote-description__source" href="<?= $link ?>">Show votes</a>
        <?php if (isset($key_vote)) { ?>
        <small class="vote-description__evidence"><?= $key_vote['summary'] ?></small>
        <?php } ?>
    <?php } ?>
</li>
