<?php
    global $hansardmajors;

    $previous_speech_time = null;

?>

<div class="debate-header regional-header regional-header--<?= $current_assembly ?>">
    <div class="regional-header__overlay"></div>
    <div class="full-page__row">
        <div class="debate-header__content full-page__unit">
            <h1><?= $heading ?></h1>
            <p class="lead">
                <?= $intro ?> <?= $location ?>
                <?php if ($debate_time_human) { ?>at <?= $debate_time_human ?><?php } ?>
                on <a href="<?= $debate_day_link ?>"><?= $debate_day_human ?></a>.
            </p>
            <p class="cta">
                <a class="button alert" href="/alerts/?alertsearch=<?= urlencode($email_alert_text) ?>">Alert me about debates like this</a>
            </p>
        </div>
    </div>
    <?php $section = true; include '_section_nav.php'; ?>
</div>

<div class="full-page">

<?php $section = true; include '_section_content.php'; ?>

</div>

<?php include '_section_footer.php'; ?>
