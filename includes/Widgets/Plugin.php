<?php

namespace Nebula\Widgets;

use Nebula\Helpers\Renderer;
use Nebula\Helpers\Validate;
use Nebula\Plugin as NebulaPlugin;
use Nebula\Widget;

class Plugin extends Widget
{
    /**
     * 插件列表
     *
     * @var null|array
     */
    private $pluginList = null;

    /**
     * 获取插件类名
     *
     * @param string 插件名
     * @return string 类名
     */
    public function getPluginClassName($pluginName)
    {
        return 'Content\Plugins\\' . $pluginName . '\Main';
    }

    /**
     * 获取插件列表
     *
     * @return array 插件列表
     */
    public function getPluginList()
    {
        if (null === $this->pluginList) {
            // 已启用插件列表
            $plugins = NebulaPlugin::export();

            // 插件目录列表
            $pluginDirs = glob(NEBULA_ROOT_PATH . 'content/plugins/*/');

            // 插件列表初始化
            $this->pluginList = array_map(function ($pluginDir) use ($plugins) {
                $pluginInfo = [];

                // 插件目录
                $pluginInfo['dir'] = basename($pluginDir);

                // 插件类名
                $pluginClassName =  $this->getPluginClassName($pluginInfo['dir']);

                // 修改启用状态
                $pluginInfo['is_activated'] = in_array($pluginClassName, array_keys($plugins));

                // 插件是否可配置
                $pluginInfo['is_config'] = false;
                if ($pluginInfo['is_activated']) {
                    $pluginInfo['is_config'] = [] !== $plugins[$pluginClassName]['config'];
                }

                // 判断插件是否完整
                $pluginIndexPath = $pluginDir . '/Main.php';
                if (file_exists($pluginIndexPath)) {
                    // 是否完整
                    $pluginInfo['is_complete'] = true;
                    $info = $this->parseHeaderInfo($pluginIndexPath);
                    $pluginInfo = array_merge($pluginInfo, $info);
                } else {
                    $pluginInfo['is_complete'] = false;
                }

                return $pluginInfo;
            }, $pluginDirs);
        }

        return $this->pluginList;
    }

    /**
     * 格式化文件头信息
     *
     * @param string $pluginPath 插件路径
     * @return array 插件信息
     */
    private function parseHeaderInfo($pluginPath)
    {
        $tokens = token_get_all(file_get_contents($pluginPath));
        $isDoc = false;
        // 初始数据
        $info = [
            'name' => '未知',
            'url' => '',
            'description' => '未知',
            'version' => '未知',
            'author' => '未知',
            'author_url' => '',
        ];

        foreach ($tokens as $token) {
            if (!$isDoc && $token[0] === T_DOC_COMMENT) {
                if (strpos($token[1], 'name')) {
                    $isDoc = true;

                    // 插件名
                    preg_match('/name:(.*)[\\r\\n]/', $token[1], $matches);
                    $info['name'] = trim($matches[1] ?? '未知');

                    // 插件地址
                    preg_match('/url:(.*)[\\r\\n]/', $token[1], $matches);
                    $info['url'] = trim($matches[1] ?? '');

                    // 插件描述
                    preg_match('/description:(.*)[\\r\\n]/', $token[1], $matches);
                    $info['description'] = trim($matches[1] ?? '未知');

                    // 插件版本
                    preg_match('/version:(.*)[\\r\\n]/', $token[1], $matches);
                    $info['version'] = trim($matches[1] ?? '未知');

                    // 插件作者
                    preg_match('/author:(.*)[\\r\\n]/', $token[1], $matches);
                    $info['author'] = trim($matches[1] ?? '未知');

                    // 插件作者地址
                    preg_match('/author_url:(.*)[\\r\\n]/', $token[1], $matches);
                    $info['author_url'] = trim($matches[1] ?? '');
                }
            }
        }

        return $info;
    }

    /**
     * 启用插件
     *
     * @return void
     */
    private function enable()
    {
        $pluginName = $this->getPluginClassName($this->params['pluginName']);

        // 已启用插件列表
        $plugins = NebulaPlugin::export();

        // 判断组件是否已启用
        if (array_key_exists($pluginName, $plugins)) {
            Notice::alloc()->set('不能重复启用插件', 'warning');
            $this->response->redirect('/admin/plugins.php');
        }

        // 判断插件是否存在异常
        if (!class_exists($pluginName) || !method_exists($pluginName, 'activate')) {
            Notice::alloc()->set('无法启用插件', 'warning');
            $this->response->redirect('/admin/plugins.php');
        }

        // 获取插件配置
        $renderer = new Renderer();
        call_user_func([$pluginName, 'config'], $renderer);
        $pluginConfig = $renderer->getValues();

        // 获取插件选项
        call_user_func([$pluginName, 'activate']);
        NebulaPlugin::activate($pluginName, $pluginConfig);

        // 提交修改
        $this->db->update('options', ['value' => serialize(NebulaPlugin::export())], ['name' => 'plugins']);

        Notice::alloc()->set('启用成功', 'success');
        $this->response->redirect('/admin/plugins.php');
    }

    /**
     * 禁用插件
     *
     * @return void
     */
    private function disabled()
    {
        // 插件类名
        $pluginClassName = $this->getPluginClassName($this->params['pluginName']);

        // 已启用插件列表
        $plugins = NebulaPlugin::export();

        // 判断组件是否已停用
        if (!isset($plugins[$pluginClassName])) {
            Notice::alloc()->set('不能重复停用插件', 'warning');
            $this->response->redirect('/admin/plugins.php');
        }

        // 判断插件是否存在异常
        if (!class_exists($pluginClassName) || !method_exists($pluginClassName, 'activate')) {
            Notice::alloc()->set('无法停用插件', 'warning');
            $this->response->redirect('/admin/plugins.php');
        }

        // 获取插件选项
        call_user_func([$pluginClassName, 'deactivate']);
        NebulaPlugin::deactivate($pluginClassName);

        // 提交修改
        $this->db->update('options', ['value' => serialize(NebulaPlugin::export())], ['name' => 'plugins']);

        Notice::alloc()->set('禁用成功', 'success');
        $this->response->redirect('/admin/plugins.php');
    }

    private function updateConfig()
    {
        // 已启用插件列表
        $plugins = NebulaPlugin::export();

        // 插件类名
        $pluginClassName = $this->getPluginClassName($this->request->post('pluginName'));

        if (null === $pluginClassName) {
            Notice::alloc()->set('插件名不能为空', 'warning');
            $this->response->redirect('/admin/plugins.php');
        }

        if (!array_key_exists($pluginClassName, $plugins)) {
            Notice::alloc()->set('插件未启用', 'warning');
            $this->response->redirect('/admin/plugins.php');
        }

        $data = $this->request->post();

        $pluginConfig = $plugins[$pluginClassName]['config'];

        $rules = [];
        foreach (array_keys($pluginConfig) as $value) {
            $rules[$value] = [['type' => 'required', 'message' => '「' . $value . '」不能为空']];
        }
        $validate = new Validate($data, $rules);
        if (!$validate->run()) {
            Notice::alloc()->set($validate->result[0]['message'], 'warning');
            $this->response->redirect('/admin/plugin-config.php?name=' . $data['pluginName']);
        }

        // 更新
        NebulaPlugin::updateConfig($pluginClassName, $data);

        // 提交修改
        $this->db->update('options', ['value' => serialize(NebulaPlugin::export())], ['name' => 'plugins']);

        Notice::alloc()->set('保存成功', 'success');
        $this->response->redirect('/admin/plugins.php');
    }

    /**
     * 插件配置
     */
    public function config()
    {
        // 插件类名
        $pluginClassName = $this->getPluginClassName($this->params['pluginName']);

        // 已启用插件列表
        $plugins = NebulaPlugin::export();

        if (!array_key_exists($pluginClassName, $plugins)) {
            Notice::alloc()->set('插件未启用', 'warning');
            $this->response->redirect('/admin/plugins.php');
        }

        // 判断插件是否具备配置功能
        if ([] === $plugins[$pluginClassName]['config']) {
            Notice::alloc()->set('配置功能不存在', 'warning');
            $this->response->redirect('/admin/plugins.php');
        }

        $renderer = new Renderer();
        call_user_func([$pluginClassName, 'config'], $renderer);
        $renderer->render($plugins[$pluginClassName]['config']);
    }

    /**
     * 行动方法
     *
     * @return $this
     */
    public function action()
    {
        $action = $this->params['action'];

        // 启用插件
        $this->on($action === 'enable')->enable();

        // 禁用插件
        $this->on($action === 'disabled')->disabled();

        // 更新插件配置
        $this->on($action === 'update-config')->updateConfig();

        return $this;
    }
}