<?php $__container->servers(['vps' => ['root@144.34.203.220 -p 29923']]); ?>

<?php $__container->startTask('task-vps', ['on' => 'vps']); ?>
    echo -e "\033[33m start deploy... \033[0m"
    cd /var/www/html/archerzdip.github.io
    git pull
    echo -e "\033[33m git pull success! \033[0m"
    bundle exec jekyll build
    echo -e "\033[32m deploy success! \033[0m"
<?php $__container->endTask(); ?>

<?php $_vars = get_defined_vars(); $__container->finished(function() use ($_vars) { extract($_vars); 
    echo "adasdasdasd"
}); ?>
