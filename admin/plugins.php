<?php include __DIR__ . '/modules/common.php'; ?>
<?php $user->inRole(['0']) || $response->redirect('/admin'); ?>
<?php include __DIR__ . '/modules/header.php'; ?>
<?php include __DIR__ . '/modules/navbar.php'; ?>
<?php $pluginList = \Nebula\Widgets\Plugin::factory()->getPluginList(); ?>
<div class="container">
    <?= \Nebula\Helpers\Template::tabs(
        [
            ['name' => '插件', 'path' => "/admin/plugins.php", 'active' => null, 'has' => true],
        ],
        $action,
        \Nebula\Plugin::factory('admin/plugins.php')->tab(['action' => $action])
    ) ?>
    <?php if (null === $action) : ?>
        <div class="table">
            <table>
                <colgroup>
                    <col width="30%">
                    <col width="20%">
                    <col width="30%">
                    <col width="20%">
                </colgroup>
                <thead>
                    <tr>
                        <th>名称</th>
                        <th>版本</th>
                        <th>作者</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pluginList as $plugin) : ?>
                        <tr>
                            <td><a href="<?= $plugin['url'] ?>" title="<?= $plugin['description'] ?>"><?= $plugin['name'] ?></a></td>
                            <td><?= $plugin['version'] ?></td>
                            <td><a href="<?= $plugin['author_url'] ?>"><?= $plugin['author'] ?></a></td>
                            <td>
                                <?php if ($plugin['is_activated']) : ?>
                                    <?php if ($plugin['is_config']) : ?>
                                        <a href="/admin/plugin-config.php?name=<?= $plugin['dir'] ?>">设置</a>
                                    <?php endif; ?>
                                    <a href="/plugin/disabled/<?= $plugin['dir'] ?>">禁用</a>
                                <?php else : ?>
                                    <a href="/plugin/enable/<?= $plugin['dir'] ?>">启用</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/modules/copyright.php'; ?>
<?php include __DIR__ . '/modules/common-js.php'; ?>
<?php include __DIR__ . '/modules/footer.php'; ?>
