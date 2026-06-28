<?php
/**
 * Editorial chrome — slim footer + close tags.
 * Pair with `includes/he-chrome.php` at the top of the page.
 * Caller is expected to `return;` after requiring this so the
 * legacy footer.php in the parent file is skipped.
 */
?>
</main>
<footer class="he-footer">
    <div class="he-footer-inner">
        <div>
            <a href="<?= base_url('rules.php') ?>">Rules</a>
            <a href="<?= base_url('bracket.php?game=dota2') ?>">Bracket</a>
            <a href="<?= base_url('contact.php') ?>">Contact</a>
            <a href="<?= base_url('terms.php') ?>">Terms</a>
            <a href="<?= base_url('privacy.php') ?>">Privacy</a>
        </div>
    </div>
</footer>
</div><!-- /.home-editorial -->
