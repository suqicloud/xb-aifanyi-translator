<?php
/**
 * Plugin Name: 小半AI文章翻译器
 * Plugin URI: https://www.jingxialai.com
 * Description: 基于AI接口的WordPress文章自动翻译插件，支持多平台AI接口对接和批量翻译
 * Version: 1.0.0
 * Author: Summer
 * License: GPL v2 or later
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('XB_AIFANYI_VERSION', '1.0.0');
define('XB_AIFANYI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('XB_AIFANYI_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * 主插件类
 */
class XB_AiFanyi_Translator {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'xb_aifanyi_settings';
        
        // 注册钩子
        register_activation_hook(__FILE__, array($this, 'xb_aifanyi_activate'));
        register_deactivation_hook(__FILE__, array($this, 'xb_aifanyi_deactivate'));
        register_uninstall_hook(__FILE__, array(__CLASS__, 'xb_aifanyi_uninstall'));
        
        add_action('admin_menu', array($this, 'xb_aifanyi_admin_menu'));
        add_action('wp_ajax_xb_aifanyi_translate', array($this, 'xb_aifanyi_ajax_translate'));
        add_action('wp_ajax_xb_aifanyi_save_settings', array($this, 'xb_aifanyi_ajax_save_settings'));
        add_action('wp_ajax_xb_aifanyi_get_posts', array($this, 'xb_aifanyi_ajax_get_posts'));
        add_action('wp_ajax_xb_aifanyi_test_connection', array($this, 'xb_aifanyi_ajax_test_connection'));
        add_action('wp_ajax_xb_aifanyi_delete_log', array($this, 'xb_aifanyi_ajax_delete_log'));
        add_action('wp_ajax_xb_aifanyi_get_logs', array($this, 'xb_aifanyi_ajax_get_logs'));
        add_action('wp_ajax_xb_aifanyi_clear_all_logs', array($this, 'xb_aifanyi_ajax_clear_all_logs'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'xb_aifanyi_plugin_action_links'));
    }
    
    /**
     * 插件激活时创建数据表
     */
    public function xb_aifanyi_activate() {
        $this->xb_aifanyi_create_tables();
        $this->xb_aifanyi_insert_default_settings();
    }
    
    /**
     * 插件停用时的清理工作
     */
    public function xb_aifanyi_deactivate() {
        // 停用暂时没有
    }
    
    /**
     * 创建数据库表
     */
    private function xb_aifanyi_create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // 创建设置表
        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            platform_name varchar(100) NOT NULL COMMENT 'AI平台名称',
            api_url text NOT NULL COMMENT 'API接口地址',
            api_key varchar(255) NOT NULL COMMENT 'API密钥',
            models text NOT NULL COMMENT 'AI模型列表，逗号分隔',
            is_active tinyint(1) DEFAULT 1 COMMENT '是否启用',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // 创建翻译记录表
        $log_table = $wpdb->prefix . 'xb_aifanyi_logs';
        $sql_log = "CREATE TABLE {$log_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL COMMENT '文章ID',
            original_title text COMMENT '原标题',
            translated_title text COMMENT '翻译后标题',
            original_content longtext COMMENT '原内容',
            translated_content longtext COMMENT '翻译后内容',
            source_lang varchar(10) DEFAULT 'zh' COMMENT '源语言',
            target_lang varchar(10) DEFAULT 'en' COMMENT '目标语言',
            platform_used varchar(100) COMMENT '使用的AI平台',
            model_used varchar(100) COMMENT '使用的AI模型',
            status varchar(20) DEFAULT 'pending' COMMENT '翻译状态',
            error_message text COMMENT '错误信息',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id)
        ) $charset_collate;";
        
        dbDelta($sql_log);
    }
    
    /**
     * 插入默认设置
     */
    private function xb_aifanyi_insert_default_settings() {
        global $wpdb;
        
        // 检查是否已有数据
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        if ($count > 0) {
            return;
        }
        
        // 插入通义千问默认配置
        $wpdb->insert(
            $this->table_name,
            array(
                'platform_name' => '通义千问',
                'api_url' => 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions',
                'api_key' => '',
                'models' => 'qwen-plus,qwen-turbo',
                'is_active' => 1
            )
        );
        
        // 插入DeepSeek默认配置
        $wpdb->insert(
            $this->table_name,
            array(
                'platform_name' => 'DeepSeek',
                'api_url' => 'https://api.deepseek.com/chat/completions',
                'api_key' => '',
                'models' => 'deepseek-chat',
                'is_active' => 0
            )
        );
    }
    
    /**
     * 添加管理菜单
     */
    public function xb_aifanyi_admin_menu() {
        add_menu_page(
            'AI文章翻译',
            'AI文章翻译',
            'manage_options',
            'xb-aifanyi-translator',
            array($this, 'xb_aifanyi_admin_page'),
            'dashicons-translation',
            30
        );
        
        add_submenu_page(
            'xb-aifanyi-translator',
            '翻译设置',
            '翻译设置',
            'manage_options',
            'xb-aifanyi-settings',
            array($this, 'xb_aifanyi_settings_page')
        );
        
        add_submenu_page(
            'xb-aifanyi-translator',
            '翻译记录',
            '翻译记录',
            'manage_options',
            'xb-aifanyi-logs',
            array($this, 'xb_aifanyi_logs_page')
        );
    }
    
    /**
     * 主管理页面
     */
    public function xb_aifanyi_admin_page() {
        include_once 'xb-aifanyi-admin.php';
    }
    
    /**
     * 设置页面
     */
    public function xb_aifanyi_settings_page() {
        include_once 'xb-aifanyi-admin.php';
    }
    
    /**
     * 翻译记录页面
     */
    public function xb_aifanyi_logs_page() {
        include_once 'xb-aifanyi-admin.php';
    }
    
    /**
     * 获取AI平台设置
     */
    public function xb_aifanyi_get_platforms() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY id ASC");
    }
    
    /**
     * 获取启用的AI平台
     */
    public function xb_aifanyi_get_active_platform() {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM {$this->table_name} WHERE is_active = 1 LIMIT 1");
    }
    
    /**
     * 写入调试日志到WordPress debug.log
     */
    private function xb_aifanyi_write_debug_log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = '[XB_AiFanyi] ' . date('Y-m-d H:i:s') . ' - ' . $message;
            error_log($log_message);
        }
    }
    
    /**
     * 测试AI平台连接
     */
    private function xb_aifanyi_test_api_connection($platform) {
        $this->xb_aifanyi_write_debug_log("开始测试AI平台连接: {$platform->platform_name}");
        
        $models = explode(',', $platform->models);
        $model = trim($models[0]); // 使用第一个模型
        
        $data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => '你好'
                )
            ),
            'stream' => false,
            'temperature' => 0.3,
            'max_tokens' => 50
        );
        
        $headers = array(
            'Authorization: Bearer ' . $platform->api_key,
            'Content-Type: application/json'
        );
        
        $this->xb_aifanyi_write_debug_log("测试请求数据: " . json_encode($data));
        $this->xb_aifanyi_write_debug_log("请求头: " . json_encode($headers));
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $platform->api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        $this->xb_aifanyi_write_debug_log("API响应状态码: {$http_code}");
        $this->xb_aifanyi_write_debug_log("API响应内容: " . $response);
        
        if ($curl_error) {
            $this->xb_aifanyi_write_debug_log("CURL错误: " . $curl_error);
            return array(
                'success' => false,
                'message' => 'CURL错误: ' . $curl_error
            );
        }
        
        if ($http_code !== 200) {
            $this->xb_aifanyi_write_debug_log("HTTP错误: 状态码 {$http_code}");
            return array(
                'success' => false,
                'message' => "HTTP错误: 状态码 {$http_code}，响应: " . substr($response, 0, 200)
            );
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->xb_aifanyi_write_debug_log("JSON解析错误: " . json_last_error_msg());
            return array(
                'success' => false,
                'message' => 'JSON解析错误: ' . json_last_error_msg()
            );
        }
        
        if (isset($result['error'])) {
            $error_msg = isset($result['error']['message']) ? $result['error']['message'] : '未知API错误';
            $this->xb_aifanyi_write_debug_log("API返回错误: " . $error_msg);
            return array(
                'success' => false,
                'message' => 'API错误: ' . $error_msg
            );
        }
        
        if (isset($result['choices'][0]['message']['content'])) {
            $ai_response = trim($result['choices'][0]['message']['content']);
            $this->xb_aifanyi_write_debug_log("AI响应内容: " . $ai_response);
            return array(
                'success' => true,
                'message' => "连接成功！AI回复: {$ai_response}"
            );
        }
        
        $this->xb_aifanyi_write_debug_log("API响应格式异常: " . $response);
        return array(
            'success' => false,
            'message' => 'API响应格式异常，请检查配置'
        );
    }
    
    /**
     * 保存设置的AJAX处理
     */
    public function xb_aifanyi_ajax_save_settings() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'xb_aifanyi_nonce')) {
            wp_die('安全验证失败');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
        }
        
        global $wpdb;
        
        $platform_id = intval($_POST['platform_id']);
        $platform_name = sanitize_text_field($_POST['platform_name']);
        $api_url = esc_url_raw($_POST['api_url']);
        $api_key = sanitize_text_field($_POST['api_key']);
        $models = sanitize_text_field($_POST['models']);
        $is_active = intval($_POST['is_active']);
        
        $this->xb_aifanyi_write_debug_log("保存平台设置: ID={$platform_id}, 名称={$platform_name}, URL={$api_url}");
        
        if ($platform_id > 0) {
            // 更新现有平台
            $result = $wpdb->update(
                $this->table_name,
                array(
                    'platform_name' => $platform_name,
                    'api_url' => $api_url,
                    'api_key' => $api_key,
                    'models' => $models,
                    'is_active' => $is_active
                ),
                array('id' => $platform_id)
            );
        } else {
            // 添加新平台
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'platform_name' => $platform_name,
                    'api_url' => $api_url,
                    'api_key' => $api_key,
                    'models' => $models,
                    'is_active' => $is_active
                )
            );
        }
        
        if ($result !== false) {
            $this->xb_aifanyi_write_debug_log("平台设置保存成功");
            wp_send_json_success('设置保存成功');
        } else {
            $this->xb_aifanyi_write_debug_log("平台设置保存失败: " . $wpdb->last_error);
            wp_send_json_error('设置保存失败: ' . $wpdb->last_error);
        }
    }
    
    /**
     * 获取文章列表的AJAX处理
     */
    public function xb_aifanyi_ajax_get_posts() {
        if (!wp_verify_nonce($_POST['nonce'], 'xb_aifanyi_nonce')) {
            wp_die('安全验证失败');
        }
        
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $post_limit = isset($_POST['post_limit']) ? intval($_POST['post_limit']) : 50;
        
        // 构建查询参数
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => $post_limit,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        // 如果指定了分类，添加分类筛选
        if ($category_id > 0) {
            $args['cat'] = $category_id;
        }
        
        $posts = get_posts($args);
        
        $post_list = array();
        foreach ($posts as $post) {
            // 获取文章分类
            $categories = get_the_category($post->ID);
            $category_names = array();
            foreach ($categories as $category) {
                $category_names[] = $category->name;
            }
            
            $post_list[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'date' => $post->post_date,
                'content_length' => mb_strlen(strip_tags($post->post_content)),
                'categories' => implode(', ', $category_names)
            );
        }
        
        $filter_info = $category_id > 0 ? "分类ID: {$category_id}, " : "";
        $this->xb_aifanyi_write_debug_log("获取文章列表成功: {$filter_info}数量限制: {$post_limit}, 实际获取: " . count($post_list) . " 篇文章");
        wp_send_json_success($post_list);
    }
    
    /**
     * 测试AI平台连接的AJAX方法
     */
    public function xb_aifanyi_ajax_test_connection() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'xb_aifanyi_nonce')) {
            wp_die('安全验证失败');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
        }
        
        $platform_id = intval($_POST['platform_id']);
        
        global $wpdb;
        $platform = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $platform_id));
        
        if (!$platform) {
            wp_send_json_error('平台配置不存在');
        }
        
        if (empty($platform->api_key)) {
            wp_send_json_error('请先设置API密钥');
        }
        
        // 测试连接
        $test_result = $this->xb_aifanyi_test_api_connection($platform);
        
        if ($test_result['success']) {
            wp_send_json_success($test_result['message']);
        } else {
            wp_send_json_error($test_result['message']);
        }
    }
    
    /**
     * 翻译处理的AJAX方法
     */
    public function xb_aifanyi_ajax_translate() {
        // 设置无限执行时间
        set_time_limit(0);
        
        // 获取请求参数（支持GET和POST）
        $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : (isset($_POST['nonce']) ? $_POST['nonce'] : '');
        $post_ids_param = isset($_GET['post_ids']) ? $_GET['post_ids'] : (isset($_POST['post_ids']) ? $_POST['post_ids'] : array());
        $source_lang = isset($_GET['source_lang']) ? $_GET['source_lang'] : (isset($_POST['source_lang']) ? $_POST['source_lang'] : 'zh');
        $target_lang = isset($_GET['target_lang']) ? $_GET['target_lang'] : (isset($_POST['target_lang']) ? $_POST['target_lang'] : 'en');
        $enable_polish = isset($_GET['enable_polish']) ? $_GET['enable_polish'] : (isset($_POST['enable_polish']) ? $_POST['enable_polish'] : 0);
        
        // 验证nonce
        if (!wp_verify_nonce($nonce, 'xb_aifanyi_nonce')) {
            $this->xb_aifanyi_write_debug_log('翻译失败: 安全验证失败 - nonce: ' . $nonce);
            echo "data: " . json_encode(array('type' => 'error', 'message' => '安全验证失败')) . "\n\n";
            flush();
            exit;
        }
        
        if (!current_user_can('edit_posts')) {
            $this->xb_aifanyi_write_debug_log('翻译失败: 权限不足');
            echo "data: " . json_encode(array('type' => 'error', 'message' => '权限不足')) . "\n\n";
            flush();
            exit;
        }
        
        // 处理文章ID参数
        if (is_string($post_ids_param)) {
            $post_ids = explode(',', $post_ids_param);
        } else {
            $post_ids = $post_ids_param;
        }
        $post_ids = array_map('intval', $post_ids);
        
        $source_lang = sanitize_text_field($source_lang);
        $target_lang = sanitize_text_field($target_lang);
        $enable_polish = intval($enable_polish);
        
        $this->xb_aifanyi_write_debug_log("开始翻译任务: 文章数量=" . count($post_ids) . ", 文章IDs=" . implode(',', $post_ids) . ", 源语言={$source_lang}, 目标语言={$target_lang}, 润色={$enable_polish}");
        
        // 获取活跃的AI平台配置
        $platform = $this->xb_aifanyi_get_active_platform();
        if (!$platform || empty($platform->api_key)) {
            $this->xb_aifanyi_write_debug_log('翻译失败: 未配置AI平台或API密钥为空');
            echo "data: " . json_encode(array('type' => 'error', 'message' => '请先配置AI平台设置')) . "\n\n";
            flush();
            exit;
        }
        
        $this->xb_aifanyi_write_debug_log("使用AI平台: {$platform->platform_name}, 模型: {$platform->models}");
        
        // 开始流式输出
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        
        foreach ($post_ids as $post_id) {
            $this->xb_aifanyi_translate_single_post($post_id, $source_lang, $target_lang, $enable_polish, $platform);
        }
        
        // 发送完成信号
        echo "data: " . json_encode(array('type' => 'complete', 'message' => '所有文章翻译完成')) . "\n\n";
        flush();
        
        $this->xb_aifanyi_write_debug_log('翻译任务完成');
        exit;
    }
    
    /**
     * 翻译单篇文章
     */
    private function xb_aifanyi_translate_single_post($post_id, $source_lang, $target_lang, $enable_polish, $platform) {
        $this->xb_aifanyi_write_debug_log("开始翻译文章ID: {$post_id}");
        
        $post = get_post($post_id);
        if (!$post) {
            $this->xb_aifanyi_write_debug_log("文章ID {$post_id} 不存在");
            echo "data: " . json_encode(array('type' => 'error', 'message' => "文章ID {$post_id} 不存在")) . "\n\n";
            flush();
            return;
        }
        
        $this->xb_aifanyi_write_debug_log("文章信息: 标题='{$post->post_title}', 内容长度=" . strlen($post->post_content));
        
        echo "data: " . json_encode(array('type' => 'start', 'post_id' => $post_id, 'title' => $post->post_title)) . "\n\n";
        flush();
        
        // 提取纯文本内容
        $clean_content = $this->xb_aifanyi_extract_text_content($post->post_content);
        $this->xb_aifanyi_write_debug_log("提取的纯文本内容: " . substr($clean_content, 0, 100) . "... (长度: " . mb_strlen($clean_content) . ")");
        
        // 构建翻译提示词
        $lang_map = array(
            'zh' => '中文',
            'en' => '英文',
            'ja' => '日文',
            'ko' => '韩文',
            'fr' => '法文',
            'de' => '德文',
            'es' => '西班牙文'
        );
        
        $source_lang_name = isset($lang_map[$source_lang]) ? $lang_map[$source_lang] : $source_lang;
        $target_lang_name = isset($lang_map[$target_lang]) ? $lang_map[$target_lang] : $target_lang;
        
        // 标题翻译提示词（简洁，不润色）
        $title_prompt = "你是一个专业的翻译专家，请将以下{$source_lang_name}标题翻译成{$target_lang_name}。要求简洁准确，保持标题的特点。请只返回翻译结果，不要包含任何解释或额外内容。";
        
        // 内容翻译提示词（根据是否启用润色来决定）
        if ($enable_polish) {
            // 检查内容长度，针对不同长度采用不同的润色策略
            $content_length = mb_strlen($clean_content);
            
            if ($content_length <= 50) {
                // 短内容润色策略
                $content_prompt = "你是一个专业的翻译和写作专家。请将以下{$source_lang_name}内容翻译成{$target_lang_name}，并进行深度润色优化。

对于这段较短的内容，请：
1. 先进行准确翻译
2. 然后对翻译结果进行润色，使用更丰富、生动的词汇
3. 适当扩展表达，让句子更加饱满有力
4. 增强语言的感染力和表现力
5. 确保语法完美，符合{$target_lang_name}的地道表达

即使内容较短，也要充分发挥润色的作用，让每个词汇都更加精准有力。请只返回最终的润色结果，不要包含翻译过程或解释。";
            } else if ($content_length <= 200) {
                // 中等长度内容润色策略
                $content_prompt = "你是一个专业的翻译和写作专家。请将以下{$source_lang_name}内容翻译成{$target_lang_name}，并进行深度润色优化。

请：
1. 进行准确翻译
2. 对翻译结果进行润色，使语言更加流畅自然
3. 丰富表达方式，使用更生动的词汇和句式
4. 适当补充细节描述，让内容更加充实
5. 优化句子结构，增强可读性
6. 确保语法正确，符合{$target_lang_name}的表达习惯

请只返回最终的润色结果，不要包含翻译过程或解释。";
            } else {
                // 长内容润色策略
                $content_prompt = "你是一个专业的翻译和写作专家。请将以下{$source_lang_name}内容翻译成{$target_lang_name}，并进行深度润色优化。

请：
1. 进行准确翻译
2. 对翻译结果进行全面润色优化
3. 保持原意不变的前提下，让语言更加流畅自然
4. 丰富表达方式，使用更生动的词汇和句式
5. 优化段落结构，增强文章的可读性和逻辑性
6. 适当补充细节描述，让内容更加充实完整
7. 确保语法正确，符合{$target_lang_name}的表达习惯
8. 提升整体的文学性和感染力

请只返回最终的润色结果，不要包含翻译过程或解释。";
            }
            
            $this->xb_aifanyi_write_debug_log("启用润色模式，内容长度: {$content_length} 字符，使用" . ($content_length <= 50 ? "短内容" : ($content_length <= 200 ? "中等长度" : "长内容")) . "润色策略");
        } else {
            // 普通翻译模式
            $content_prompt = "你是一个专业的翻译专家，请将以下{$source_lang_name}内容翻译成{$target_lang_name}。要求准确翻译，保持原意，语法正确。请只返回翻译结果，不要包含任何解释或额外内容。";
        }
        
        // 翻译标题
        $this->xb_aifanyi_write_debug_log("开始翻译标题: " . $post->post_title);
        $translated_title = $this->xb_aifanyi_call_ai_api($platform, $title_prompt, $post->post_title);
        if (!$translated_title) {
            $this->xb_aifanyi_write_debug_log("标题翻译失败");
            echo "data: " . json_encode(array('type' => 'error', 'message' => "标题翻译失败")) . "\n\n";
            flush();
            return;
        }
        
        $this->xb_aifanyi_write_debug_log("标题翻译完成: " . $translated_title);
        echo "data: " . json_encode(array('type' => 'title_translated', 'title' => $translated_title)) . "\n\n";
        flush();
        
        // 翻译内容
        $this->xb_aifanyi_write_debug_log("开始翻译内容" . ($enable_polish ? "（启用润色优化）" : ""));
        $translated_content = $this->xb_aifanyi_call_ai_api($platform, $content_prompt, $clean_content);
        if (!$translated_content) {
            $this->xb_aifanyi_write_debug_log("内容翻译失败");
            echo "data: " . json_encode(array('type' => 'error', 'message' => "内容翻译失败")) . "\n\n";
            flush();
            return;
        }
        
        $this->xb_aifanyi_write_debug_log("内容翻译完成: " . substr($translated_content, 0, 100) . "... (长度: " . mb_strlen($translated_content) . ")");
        
        // 重新组装内容，保留原有的HTML结构和链接
        $final_content = $this->xb_aifanyi_merge_translated_content($post->post_content, $translated_content);
        
        // 更新文章
        $updated_post = array(
            'ID' => $post_id,
            'post_title' => $translated_title,
            'post_content' => $final_content
        );
        
        $this->xb_aifanyi_write_debug_log("准备更新文章: ID={$post_id}, 新标题='{$translated_title}', 新内容长度=" . strlen($final_content));
        
        $result = wp_update_post($updated_post);
        
        if ($result && $result !== 0) {
            // 记录翻译日志
            $this->xb_aifanyi_log_translation($post_id, $post->post_title, $translated_title, 
                $clean_content, $translated_content, $source_lang, $target_lang, $platform);
            
            $this->xb_aifanyi_write_debug_log("文章ID {$post_id} 翻译并更新成功");
            echo "data: " . json_encode(array('type' => 'success', 'post_id' => $post_id, 'message' => '翻译完成')) . "\n\n";
        } else {
            $this->xb_aifanyi_write_debug_log("文章ID {$post_id} 更新失败，wp_update_post返回: " . var_export($result, true));
            echo "data: " . json_encode(array('type' => 'error', 'message' => '文章更新失败')) . "\n\n";
        }
        
        flush();
        sleep(1); // 避免API调用过于频繁
    }
    
    /**
     * 提取文章中的纯文本内容
     */
    private function xb_aifanyi_extract_text_content($content) {
        // 移除HTML标签但保留文本
        $text = wp_strip_all_tags($content);
        
        // 移除多余的空白字符
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * 将翻译后的内容重新合并到原HTML结构中
     */
    private function xb_aifanyi_merge_translated_content($original_content, $translated_text) {
        $this->xb_aifanyi_write_debug_log("开始合并翻译内容，原内容长度: " . strlen($original_content) . ", 翻译内容长度: " . strlen($translated_text));
        
        // 检查原内容是否包含HTML标签
        $original_stripped = wp_strip_all_tags($original_content);
        $has_html = (strlen($original_content) > strlen($original_stripped) * 1.1);
        
        if (!$has_html) {
            // 如果原内容主要是纯文本，直接使用翻译结果并添加段落标签
            $this->xb_aifanyi_write_debug_log("原内容为纯文本，直接替换");
            return wpautop($translated_text);
        }
        
        // 如果包含HTML，尝试智能替换
        $this->xb_aifanyi_write_debug_log("原内容包含HTML，尝试智能替换");
        
        // 简单策略：保留HTML结构，但替换主要文本内容
        // 先尝试按段落分割
        $paragraphs = explode("\n\n", $translated_text);
        $result_content = '';
        
        // 如果原内容有段落标签，按段落替换
        if (strpos($original_content, '<p>') !== false) {
            $paragraph_index = 0;
            $result_content = preg_replace_callback('/<p[^>]*>(.*?)<\/p>/s', function($matches) use ($paragraphs, &$paragraph_index) {
                if ($paragraph_index < count($paragraphs) && !empty(trim($paragraphs[$paragraph_index]))) {
                    $translated_p = trim($paragraphs[$paragraph_index]);
                    $paragraph_index++;
                    return '<p>' . $translated_p . '</p>';
                }
                return $matches[0]; // 保持原样
            }, $original_content);
            
            // 如果还有剩余的翻译段落，添加到末尾
            while ($paragraph_index < count($paragraphs)) {
                if (!empty(trim($paragraphs[$paragraph_index]))) {
                    $result_content .= '<p>' . trim($paragraphs[$paragraph_index]) . '</p>';
                }
                $paragraph_index++;
            }
        } else {
            // 如果没有段落标签，直接用翻译内容替换，但保留链接和图片
            $result_content = $original_content;
            
            // 提取链接和图片
            preg_match_all('/<a[^>]*>.*?<\/a>|<img[^>]*>|<video[^>]*>.*?<\/video>|<audio[^>]*>.*?<\/audio>/s', $original_content, $media_matches);
            
            // 用翻译内容替换，但保留媒体元素
            $result_content = wpautop($translated_text);
            
            // 如果有媒体元素，尝试重新插入
            if (!empty($media_matches[0])) {
                foreach ($media_matches[0] as $media) {
                    $result_content .= "\n" . $media;
                }
            }
        }
        
        $this->xb_aifanyi_write_debug_log("内容合并完成，结果长度: " . strlen($result_content));
        return $result_content;
    }
    
    /**
     * 调用AI API进行翻译
     */
    private function xb_aifanyi_call_ai_api($platform, $system_prompt, $content) {
        $models = explode(',', $platform->models);
        $model = trim($models[0]); // 使用第一个模型
        
        $data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => $content
                )
            ),
            'stream' => false,
            'temperature' => 0.3
        );
        
        $headers = array(
            'Authorization: Bearer ' . $platform->api_key,
            'Content-Type: application/json'
        );
        
        $this->xb_aifanyi_write_debug_log("调用AI API: 模型={$model}, 内容长度=" . mb_strlen($content) . ", 内容预览: " . substr($content, 0, 50) . "...");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $platform->api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            $this->xb_aifanyi_write_debug_log("CURL错误: " . $curl_error);
            return false;
        }
        
        if ($http_code !== 200) {
            $this->xb_aifanyi_write_debug_log("AI API调用失败: HTTP {$http_code}, Response: " . substr($response, 0, 500));
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->xb_aifanyi_write_debug_log("JSON解析错误: " . json_last_error_msg());
            return false;
        }
        
        if (isset($result['error'])) {
            $error_msg = isset($result['error']['message']) ? $result['error']['message'] : '未知API错误';
            $this->xb_aifanyi_write_debug_log("API返回错误: " . $error_msg);
            return false;
        }
        
        if (isset($result['choices'][0]['message']['content'])) {
            $ai_response = trim($result['choices'][0]['message']['content']);
            $this->xb_aifanyi_write_debug_log("AI API调用成功，响应长度: " . mb_strlen($ai_response) . ", 响应预览: " . substr($ai_response, 0, 50) . "...");
            return $ai_response;
        }
        
        $this->xb_aifanyi_write_debug_log("AI API响应格式错误: " . substr($response, 0, 500));
        return false;
    }
    
    /**
     * 记录翻译日志
     */
    private function xb_aifanyi_log_translation($post_id, $original_title, $translated_title, 
        $original_content, $translated_content, $source_lang, $target_lang, $platform) {
        
        global $wpdb;
        $log_table = $wpdb->prefix . 'xb_aifanyi_logs';
        
        $models = explode(',', $platform->models);
        $model = trim($models[0]);
        
        $result = $wpdb->insert(
            $log_table,
            array(
                'post_id' => $post_id,
                'original_title' => $original_title,
                'translated_title' => $translated_title,
                'original_content' => $original_content,
                'translated_content' => $translated_content,
                'source_lang' => $source_lang,
                'target_lang' => $target_lang,
                'platform_used' => $platform->platform_name,
                'model_used' => $model,
                'status' => 'completed'
            )
        );
        
        if ($result) {
            $this->xb_aifanyi_write_debug_log("翻译日志记录成功: 文章ID {$post_id}");
        } else {
            $this->xb_aifanyi_write_debug_log("翻译日志记录失败: " . $wpdb->last_error);
        }
    }
    
    /**
     * 获取翻译记录的AJAX处理
     */
    public function xb_aifanyi_ajax_get_logs() {
        if (!wp_verify_nonce($_POST['nonce'], 'xb_aifanyi_nonce')) {
            wp_die('安全验证失败');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
        }
        
        global $wpdb;
        $log_table = $wpdb->prefix . 'xb_aifanyi_logs';
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        // 获取总记录数
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$log_table}");
        
        // 获取记录列表
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$log_table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        $log_list = array();
        foreach ($logs as $log) {
            $post_title = get_the_title($log->post_id);
            if (empty($post_title)) {
                $post_title = '文章已删除';
            }
            
            $log_list[] = array(
                'id' => $log->id,
                'post_id' => $log->post_id,
                'post_title' => $post_title,
                'original_title' => $log->original_title,
                'translated_title' => $log->translated_title,
                'source_lang' => $log->source_lang,
                'target_lang' => $log->target_lang,
                'platform_used' => $log->platform_used,
                'model_used' => $log->model_used,
                'status' => $log->status,
                'created_at' => $log->created_at,
                'original_content_length' => mb_strlen($log->original_content),
                'translated_content_length' => mb_strlen($log->translated_content)
            );
        }
        
        wp_send_json_success(array(
            'logs' => $log_list,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ));
    }
    
    /**
     * 删除翻译记录的AJAX处理
     */
    public function xb_aifanyi_ajax_delete_log() {
        if (!wp_verify_nonce($_POST['nonce'], 'xb_aifanyi_nonce')) {
            wp_die('安全验证失败');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
        }
        
        $log_id = intval($_POST['log_id']);
        
        global $wpdb;
        $log_table = $wpdb->prefix . 'xb_aifanyi_logs';
        
        $result = $wpdb->delete($log_table, array('id' => $log_id), array('%d'));
        
        if ($result !== false) {
            $this->xb_aifanyi_write_debug_log("翻译记录删除成功: ID {$log_id}");
            wp_send_json_success('记录删除成功');
        } else {
            $this->xb_aifanyi_write_debug_log("翻译记录删除失败: " . $wpdb->last_error);
            wp_send_json_error('记录删除失败: ' . $wpdb->last_error);
        }
    }

    /**
     * 清空所有翻译记录的AJAX处理
     */
    public function xb_aifanyi_ajax_clear_all_logs() {
        if (!wp_verify_nonce($_POST['nonce'], 'xb_aifanyi_nonce')) {
            wp_die('安全验证失败');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
        }
        
        global $wpdb;
        $log_table = $wpdb->prefix . 'xb_aifanyi_logs';
        
        // 获取清空前的记录总数
        $total_before = $wpdb->get_var("SELECT COUNT(*) FROM {$log_table}");
        
        // 清空所有记录
        $result = $wpdb->query("TRUNCATE TABLE {$log_table}");
        
        if ($result !== false) {
            $this->xb_aifanyi_write_debug_log("成功清空所有翻译记录，共清空 {$total_before} 条记录");
            wp_send_json_success("成功清空所有翻译记录，共清空 {$total_before} 条记录");
        } else {
            $this->xb_aifanyi_write_debug_log("清空翻译记录失败: " . $wpdb->last_error);
            wp_send_json_error('清空记录失败: ' . $wpdb->last_error);
        }
    }

    /**
     * 添加插件设置页面链接
     */
    public function xb_aifanyi_plugin_action_links($links) {
        $settings_link = '<a href="admin.php?page=xb-aifanyi-settings">设置</a>';
        $main_link = '<a href="admin.php?page=xb-aifanyi-translator">翻译文章</a>';
        
        // 将设置链接添加到数组开头
        array_unshift($links, $settings_link);
        array_unshift($links, $main_link);
        
        return $links;
    }


    /**
     * 插件卸载时的清理工作
     */
    public static function xb_aifanyi_uninstall() {
        global $wpdb;
    
        // 删除插件相关的数据表
        $settings_table = $wpdb->prefix . 'xb_aifanyi_settings';
        $logs_table = $wpdb->prefix . 'xb_aifanyi_logs';
    
        // 删除设置表
        $wpdb->query("DROP TABLE IF EXISTS {$settings_table}");
    
        // 删除日志表
        $wpdb->query("DROP TABLE IF EXISTS {$logs_table}");
    
        // 写入调试日志
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[XB_AiFanyi] 插件卸载完成，数据表已删除');
        }
    }    
}

// 初始化插件
new XB_AiFanyi_Translator();