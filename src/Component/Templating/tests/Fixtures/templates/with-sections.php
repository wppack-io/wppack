<?php $view->layout('layouts/two-column'); ?>
<?php $view->start('sidebar'); ?>
<nav>Sidebar content</nav>
<?php $view->stop(); ?>
<article><?= $view->e($title) ?></article>
