<?php
// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 获取当前页面
$current_page = isset($_GET['page']) ? $_GET['page'] : 'xb-aifanyi-translator';
?>

<div class="wrap">
    <h1>AI文章翻译器</h1>
    
    <nav class="nav-tab-wrapper">
        <a href="?page=xb-aifanyi-translator" class="nav-tab <?php echo $current_page === 'xb-aifanyi-translator' ? 'nav-tab-active' : ''; ?>">文章翻译</a>
        <a href="?page=xb-aifanyi-settings" class="nav-tab <?php echo $current_page === 'xb-aifanyi-settings' ? 'nav-tab-active' : ''; ?>">AI平台设置</a>
        <a href="?page=xb-aifanyi-logs" class="nav-tab <?php echo $current_page === 'xb-aifanyi-logs' ? 'nav-tab-active' : ''; ?>">翻译记录</a>
    </nav>
    
    <?php if ($current_page === 'xb-aifanyi-translator'): ?>
        <!-- 文章翻译页面 -->
        <div class="xb-aifanyi-translator-page">
            <h2>选择要翻译的文章</h2>
            
            <div class="xb-aifanyi-controls">
                <div class="xb-aifanyi-control-group">
                    <label>源语言：</label>
                    <select id="xb-aifanyi-source-lang">
                        <option value="zh">中文</option>
                        <option value="en">英文</option>
                        <option value="ja">日文</option>
                        <option value="ko">韩文</option>
                        <option value="fr">法文</option>
                        <option value="de">德文</option>
                        <option value="es">西班牙文</option>
                    </select>
                </div>
                
                <div class="xb-aifanyi-control-group">
                    <label>目标语言：</label>
                    <select id="xb-aifanyi-target-lang">
                        <option value="en">英文</option>
                        <option value="zh">中文</option>
                        <option value="ja">日文</option>
                        <option value="ko">韩文</option>
                        <option value="fr">法文</option>
                        <option value="de">德文</option>
                        <option value="es">西班牙文</option>
                    </select>
                </div>
                
                <div class="xb-aifanyi-control-group">
                    <label>
                        <input type="checkbox" id="xb-aifanyi-enable-polish" checked>
                        启用内容润色优化
                    </label>
                </div>
            </div>
            
            <div class="xb-aifanyi-post-list">
                <div class="xb-aifanyi-list-header">
                    <div class="xb-aifanyi-filter-controls">
                        <label>按分类筛选：</label>
                        <select id="xb-aifanyi-category-filter">
                            <option value="">所有分类</option>
                            <?php
                            $categories = get_categories(array('hide_empty' => false));
                            foreach ($categories as $category) {
                                echo '<option value="' . $category->term_id . '">' . esc_html($category->name) . ' (' . $category->count . ')</option>';
                            }
                            ?>
                        </select>
                        
                        <label>文章数量：</label>
                        <select id="xb-aifanyi-post-limit">
                            <option value="20">20篇</option>
                            <option value="50" selected>50篇</option>
                            <option value="100">100篇</option>
                            <option value="-1">全部</option>
                        </select>
                    </div>
                    
                    <div class="xb-aifanyi-action-controls">
                        <label>
                            <input type="checkbox" id="xb-aifanyi-select-all">
                            全选
                        </label>
                        <button type="button" id="xb-aifanyi-load-posts" class="button">加载文章列表</button>
                        <button type="button" id="xb-aifanyi-start-translate" class="button button-primary" disabled>开始翻译</button>
                    </div>
                </div>
                
                <div id="xb-aifanyi-posts-container">
                    <p>点击"加载文章列表"来显示可翻译的文章</p>
                </div>
            </div>
            
            <div id="xb-aifanyi-progress" style="display: none;">
                <h3>翻译进度</h3>
                <div id="xb-aifanyi-progress-bar">
                    <div id="xb-aifanyi-progress-fill"></div>
                </div>
                <div id="xb-aifanyi-progress-text"></div>
                <div id="xb-aifanyi-log-container"></div>
            </div>
        </div>
        
    <?php elseif ($current_page === 'xb-aifanyi-settings'): ?>
        <!-- AI平台设置页面 -->
        <div class="xb-aifanyi-settings-page">
            <h2>AI平台配置</h2>
            
            <div id="xb-aifanyi-platforms-list">
                <?php
                $translator = new XB_AiFanyi_Translator();
                $platforms = $translator->xb_aifanyi_get_platforms();
                
                foreach ($platforms as $platform):
                ?>
                <div class="xb-aifanyi-platform-item" data-id="<?php echo $platform->id; ?>">
                    <h3><?php echo esc_html($platform->platform_name); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th>平台名称</th>
                            <td>
                                <input type="text" name="platform_name" value="<?php echo esc_attr($platform->platform_name); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th>API接口地址</th>
                            <td>
                                <input type="url" name="api_url" value="<?php echo esc_attr($platform->api_url); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th>API密钥</th>
                            <td>
                                <input type="password" name="api_key" value="<?php echo esc_attr($platform->api_key); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th>AI模型</th>
                            <td>
                                <input type="text" name="models" value="<?php echo esc_attr($platform->models); ?>" class="regular-text">
                                <p class="description">多个模型用英文逗号分隔，如：qwen-plus,qwen-turbo</p>
                            </td>
                        </tr>
                        <tr>
                            <th>启用状态</th>
                            <td>
                                <label>
                                    <input type="radio" name="is_active" value="<?php echo $platform->id; ?>" <?php checked($platform->is_active, 1); ?>>
                                    启用此平台
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="button" class="button button-primary xb-aifanyi-save-platform" data-id="<?php echo $platform->id; ?>">保存设置</button>
                        <button type="button" class="button xb-aifanyi-test-platform" data-id="<?php echo $platform->id; ?>">测试连接</button>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="xb-aifanyi-add-platform">
                <h3>添加新平台</h3>
                <div class="xb-aifanyi-platform-item" data-id="0">
                    <table class="form-table">
                        <tr>
                            <th>平台名称</th>
                            <td>
                                <input type="text" name="platform_name" value="" class="regular-text" placeholder="如：ChatGPT">
                            </td>
                        </tr>
                        <tr>
                            <th>API接口地址</th>
                            <td>
                                <input type="url" name="api_url" value="" class="regular-text" placeholder="https://api.openai.com/v1/chat/completions">
                            </td>
                        </tr>
                        <tr>
                            <th>API密钥</th>
                            <td>
                                <input type="password" name="api_key" value="" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th>AI模型</th>
                            <td>
                                <input type="text" name="models" value="" class="regular-text" placeholder="gpt-3.5-turbo,gpt-4">
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="button" class="button button-primary xb-aifanyi-save-platform" data-id="0">添加平台</button>
                    </p>
                </div>
            </div>
        </div>
        
    <?php elseif ($current_page === 'xb-aifanyi-logs'): ?>
        <!-- 翻译记录页面 -->
        <div class="xb-aifanyi-logs-page">
            <h2>翻译记录管理</h2>
            
            <div class="xb-aifanyi-logs-controls">
                <button type="button" id="xb-aifanyi-load-logs" class="button">加载记录</button>
                <button type="button" id="xb-aifanyi-clear-all-logs" class="button button-secondary">清空所有记录</button>
            </div>
            
            <div id="xb-aifanyi-logs-container">
                <p>点击"加载记录"来显示翻译历史记录</p>
            </div>
            
            <div id="xb-aifanyi-logs-pagination" style="display: none;">
                <button type="button" id="xb-aifanyi-prev-page" class="button" disabled>上一页</button>
                <span id="xb-aifanyi-page-info"></span>
                <button type="button" id="xb-aifanyi-next-page" class="button" disabled>下一页</button>
            </div>
        </div>
        
    <?php endif; ?>
</div>

<style>
.xb-aifanyi-controls {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.xb-aifanyi-control-group {
    display: inline-block;
    margin-right: 20px;
    margin-bottom: 10px;
}

.xb-aifanyi-control-group label {
    font-weight: 600;
    margin-right: 8px;
}

.xb-aifanyi-post-list {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin: 20px 0;
}

.xb-aifanyi-list-header {
    padding: 15px 20px;
    border-bottom: 1px solid #ccd0d4;
    background: #f9f9f9;
}

.xb-aifanyi-filter-controls {
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ddd;
}

.xb-aifanyi-filter-controls label {
    font-weight: 600;
    margin-right: 8px;
    margin-left: 15px;
}

.xb-aifanyi-filter-controls label:first-child {
    margin-left: 0;
}

.xb-aifanyi-filter-controls select {
    margin-right: 15px;
}

.xb-aifanyi-action-controls label {
    margin-right: 15px;
}

.xb-aifanyi-action-controls button {
    margin-left: 10px;
}

#xb-aifanyi-posts-container {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.xb-aifanyi-post-item {
    padding: 10px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
}

.xb-aifanyi-post-item:last-child {
    border-bottom: none;
}

.xb-aifanyi-post-item input[type="checkbox"] {
    margin-right: 10px;
}

.xb-aifanyi-post-info {
    flex: 1;
}

.xb-aifanyi-post-title {
    font-weight: 600;
    margin-bottom: 5px;
}

.xb-aifanyi-post-meta {
    color: #666;
    font-size: 12px;
}

#xb-aifanyi-progress {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin: 20px 0;
}

#xb-aifanyi-progress-bar {
    width: 100%;
    height: 20px;
    background: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
    margin: 10px 0;
}

#xb-aifanyi-progress-fill {
    height: 100%;
    background: #0073aa;
    width: 0%;
    transition: width 0.3s ease;
}

#xb-aifanyi-log-container {
    max-height: 300px;
    overflow-y: auto;
    background: #f9f9f9;
    padding: 10px;
    border-radius: 4px;
    margin-top: 15px;
    font-family: monospace;
    font-size: 12px;
}

.xb-aifanyi-log-item {
    margin-bottom: 5px;
    padding: 5px;
    border-radius: 3px;
}

.xb-aifanyi-log-success {
    background: #d4edda;
    color: #155724;
}

.xb-aifanyi-log-error {
    background: #f8d7da;
    color: #721c24;
}

.xb-aifanyi-log-info {
    background: #d1ecf1;
    color: #0c5460;
}

.xb-aifanyi-platform-item {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.xb-aifanyi-platform-item h3 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.xb-aifanyi-message {
    padding: 10px 15px;
    margin: 10px 0;
    border-radius: 4px;
}

.xb-aifanyi-message.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.xb-aifanyi-message.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.xb-aifanyi-logs-page {
    margin-top: 20px;
}

.xb-aifanyi-logs-controls {
    background: #fff;
    padding: 15px 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.xb-aifanyi-logs-controls button {
    margin-right: 10px;
}

.xb-aifanyi-logs-table {
    width: 100%;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin: 20px 0;
}

.xb-aifanyi-logs-table table {
    width: 100%;
    border-collapse: collapse;
}

.xb-aifanyi-logs-table th,
.xb-aifanyi-logs-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.xb-aifanyi-logs-table th {
    background: #f9f9f9;
    font-weight: 600;
}

.xb-aifanyi-logs-table tr:hover {
    background: #f5f5f5;
}

.xb-aifanyi-log-actions {
    white-space: nowrap;
}

.xb-aifanyi-log-actions button {
    margin-right: 5px;
}

#xb-aifanyi-logs-pagination {
    text-align: center;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin: 20px 0;
}

#xb-aifanyi-logs-pagination button {
    margin: 0 10px;
}

#xb-aifanyi-page-info {
    margin: 0 15px;
    font-weight: 600;
}

.xb-aifanyi-lang-badge {
    display: inline-block;
    padding: 2px 6px;
    background: #0073aa;
    color: white;
    border-radius: 3px;
    font-size: 11px;
    margin-right: 3px;
}

.xb-aifanyi-status-completed {
    color: #46b450;
    font-weight: 600;
}

.xb-aifanyi-status-failed {
    color: #dc3232;
    font-weight: 600;
}
</style>

<script>
jQuery(document).ready(function($) {
    const nonce = '<?php echo wp_create_nonce('xb_aifanyi_nonce'); ?>';
    
    // 加载文章列表
    $('#xb-aifanyi-load-posts').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('加载中...');
        
        const categoryId = $('#xb-aifanyi-category-filter').val();
        const postLimit = $('#xb-aifanyi-post-limit').val();
        
        $.post(ajaxurl, {
            action: 'xb_aifanyi_get_posts',
            nonce: nonce,
            category_id: categoryId,
            post_limit: postLimit
        }, function(response) {
            if (response.success) {
                let html = '';
                if (response.data.length === 0) {
                    html = '<p>没有找到符合条件的文章</p>';
                } else {
                    response.data.forEach(function(post) {
                        html += `
                            <div class="xb-aifanyi-post-item">
                                <input type="checkbox" name="post_ids[]" value="${post.id}">
                                <div class="xb-aifanyi-post-info">
                                    <div class="xb-aifanyi-post-title">${post.title}</div>
                                    <div class="xb-aifanyi-post-meta">
                                        发布时间: ${post.date} | 内容长度: ${post.content_length} 字符 | 分类: ${post.categories}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
                $('#xb-aifanyi-posts-container').html(html);
                $('#xb-aifanyi-start-translate').prop('disabled', response.data.length === 0);
            } else {
                showMessage('加载文章列表失败: ' + response.data, 'error');
            }
        }).always(function() {
            button.prop('disabled', false).text('加载文章列表');
        });
    });
    
    // 全选功能
    $('#xb-aifanyi-select-all').on('change', function() {
        $('input[name="post_ids[]"]').prop('checked', $(this).is(':checked'));
    });
    
    // 开始翻译
    $('#xb-aifanyi-start-translate').on('click', function() {
        const selectedPosts = $('input[name="post_ids[]"]:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (selectedPosts.length === 0) {
            showMessage('请选择要翻译的文章', 'error');
            return;
        }
        
        const sourceLang = $('#xb-aifanyi-source-lang').val();
        const targetLang = $('#xb-aifanyi-target-lang').val();
        const enablePolish = $('#xb-aifanyi-enable-polish').is(':checked') ? 1 : 0;
        
        if (sourceLang === targetLang) {
            showMessage('源语言和目标语言不能相同', 'error');
            return;
        }
        
        // 显示进度区域
        $('#xb-aifanyi-progress').show();
        $('#xb-aifanyi-progress-text').text('准备开始翻译...');
        $('#xb-aifanyi-log-container').empty();
        
        // 禁用翻译按钮
        $(this).prop('disabled', true).text('翻译中...');
        
        // 开始流式翻译
        startStreamTranslation(selectedPosts, sourceLang, targetLang, enablePolish);
    });
    
    // 流式翻译处理
    function startStreamTranslation(postIds, sourceLang, targetLang, enablePolish) {
        const eventSource = new EventSource(ajaxurl + '?' + $.param({
            action: 'xb_aifanyi_translate',
            post_ids: postIds.join(','),
            source_lang: sourceLang,
            target_lang: targetLang,
            enable_polish: enablePolish,
            nonce: nonce
        }));
        
        let completedCount = 0;
        const totalCount = postIds.length;
        
        eventSource.onmessage = function(event) {
            const data = JSON.parse(event.data);
            
            switch(data.type) {
                case 'start':
                    addLogItem(`开始翻译文章: ${data.title}`, 'info');
                    break;
                    
                case 'title_translated':
                    addLogItem(`标题翻译完成: ${data.title}`, 'info');
                    break;
                    
                case 'success':
                    completedCount++;
                    addLogItem(`文章翻译完成: ${data.message}`, 'success');
                    updateProgress(completedCount, totalCount);
                    break;
                    
                case 'error':
                    addLogItem(`翻译错误: ${data.message}`, 'error');
                    break;
                    
                case 'complete':
                    addLogItem(data.message, 'success');
                    $('#xb-aifanyi-start-translate').prop('disabled', false).text('开始翻译');
                    eventSource.close();
                    break;
            }
        };
        
        eventSource.onerror = function() {
            addLogItem('连接中断，翻译可能未完成', 'error');
            $('#xb-aifanyi-start-translate').prop('disabled', false).text('开始翻译');
            eventSource.close();
        };
    }
    
    // 更新进度条
    function updateProgress(completed, total) {
        const percentage = (completed / total) * 100;
        $('#xb-aifanyi-progress-fill').css('width', percentage + '%');
        $('#xb-aifanyi-progress-text').text(`翻译进度: ${completed}/${total} (${Math.round(percentage)}%)`);
    }
    
    // 添加日志项
    function addLogItem(message, type) {
        const timestamp = new Date().toLocaleTimeString();
        const logItem = $(`<div class="xb-aifanyi-log-item xb-aifanyi-log-${type}">[${timestamp}] ${message}</div>`);
        $('#xb-aifanyi-log-container').append(logItem);
        $('#xb-aifanyi-log-container').scrollTop($('#xb-aifanyi-log-container')[0].scrollHeight);
    }
    
    // 保存平台设置
    $('.xb-aifanyi-save-platform').on('click', function() {
        const button = $(this);
        const platformItem = button.closest('.xb-aifanyi-platform-item');
        const platformId = platformItem.data('id');
        
        const data = {
            action: 'xb_aifanyi_save_settings',
            nonce: nonce,
            platform_id: platformId,
            platform_name: platformItem.find('input[name="platform_name"]').val(),
            api_url: platformItem.find('input[name="api_url"]').val(),
            api_key: platformItem.find('input[name="api_key"]').val(),
            models: platformItem.find('input[name="models"]').val(),
            is_active: $('input[name="is_active"]:checked').val() == platformId ? 1 : 0
        };
        
        button.prop('disabled', true).text('保存中...');
        
        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                showMessage('设置保存成功', 'success');
                if (platformId === 0) {
                    // 新添加的平台，刷新页面
                    setTimeout(() => location.reload(), 1000);
                }
            } else {
                showMessage('设置保存失败: ' + response.data, 'error');
            }
        }).always(function() {
            button.prop('disabled', false).text(platformId === 0 ? '添加平台' : '保存设置');
        });
    });
    
    // 测试平台连接
    $('.xb-aifanyi-test-platform').on('click', function() {
        const button = $(this);
        const platformItem = button.closest('.xb-aifanyi-platform-item');
        const platformId = platformItem.data('id');
        
        button.prop('disabled', true).text('测试中...');
        
        $.post(ajaxurl, {
            action: 'xb_aifanyi_test_connection',
            nonce: nonce,
            platform_id: platformId
        }, function(response) {
            if (response.success) {
                showMessage('测试成功: ' + response.data, 'success');
            } else {
                showMessage('测试失败: ' + response.data, 'error');
            }
        }).fail(function() {
            showMessage('测试请求失败，请检查网络连接', 'error');
        }).always(function() {
            button.prop('disabled', false).text('测试连接');
        });
    });
    
    // 显示消息
    function showMessage(message, type) {
        const messageDiv = $(`<div class="xb-aifanyi-message ${type}">${message}</div>`);
        $('.wrap h1').after(messageDiv);
        
        setTimeout(function() {
            messageDiv.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // 翻译记录管理相关功能
    let currentPage = 1;
    
    // 加载翻译记录
    $('#xb-aifanyi-load-logs').on('click', function() {
        loadLogs(1);
    });
    
    // 分页功能
    $('#xb-aifanyi-prev-page').on('click', function() {
        if (currentPage > 1) {
            loadLogs(currentPage - 1);
        }
    });
    
    $('#xb-aifanyi-next-page').on('click', function() {
        loadLogs(currentPage + 1);
    });
    
    // 清空所有记录
    $('#xb-aifanyi-clear-all-logs').on('click', function() {
        if (confirm('确定要清空所有翻译记录吗？此操作不可恢复！')) {
            clearAllLogs();
        }
    });
    
    // 加载记录函数
    function loadLogs(page) {
        const button = $('#xb-aifanyi-load-logs');
        button.prop('disabled', true).text('加载中...');
        
        $.post(ajaxurl, {
            action: 'xb_aifanyi_get_logs',
            nonce: nonce,
            page: page
        }, function(response) {
            if (response.success) {
                displayLogs(response.data);
                currentPage = response.data.page;
                updatePagination(response.data);
            } else {
                showMessage('加载记录失败: ' + response.data, 'error');
            }
        }).always(function() {
            button.prop('disabled', false).text('加载记录');
        });
    }
    
    // 显示记录
    function displayLogs(data) {
        if (data.logs.length === 0) {
            $('#xb-aifanyi-logs-container').html('<p>暂无翻译记录</p>');
            return;
        }
        
        let html = `
            <div class="xb-aifanyi-logs-table">
                <table>
                    <thead>
                        <tr>
                            <th>文章标题</th>
                            <th>翻译方向</th>
                            <th>AI平台</th>
                            <th>翻译时间</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        data.logs.forEach(function(log) {
            const langMap = {
                'zh': '中文', 'en': '英文', 'ja': '日文', 'ko': '韩文',
                'fr': '法文', 'de': '德文', 'es': '西班牙文'
            };
            
            const sourceLang = langMap[log.source_lang] || log.source_lang;
            const targetLang = langMap[log.target_lang] || log.target_lang;
            
            html += `
                <tr>
                    <td>
                        <strong>${log.original_title}</strong><br>
                        <small>→ ${log.translated_title}</small><br>
                        <small style="color: #666;">文章ID: ${log.post_id} | 内容: ${log.original_content_length} → ${log.translated_content_length} 字符</small>
                    </td>
                    <td>
                        <span class="xb-aifanyi-lang-badge">${sourceLang}</span>
                        →
                        <span class="xb-aifanyi-lang-badge">${targetLang}</span>
                    </td>
                    <td>
                        ${log.platform_used}<br>
                        <small style="color: #666;">${log.model_used}</small>
                    </td>
                    <td>${log.created_at}</td>
                    <td>
                        <span class="xb-aifanyi-status-${log.status}">${log.status === 'completed' ? '已完成' : '失败'}</span>
                    </td>
                    <td class="xb-aifanyi-log-actions">
                        <button type="button" class="button button-small button-link-delete xb-aifanyi-delete-log" data-id="${log.id}">删除</button>
                    </td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
        
        $('#xb-aifanyi-logs-container').html(html);
        
        // 绑定删除事件
        $('.xb-aifanyi-delete-log').on('click', function() {
            const logId = $(this).data('id');
            if (confirm('确定要删除这条翻译记录吗？')) {
                deleteLog(logId);
            }
        });
    }
    
    // 更新分页
    function updatePagination(data) {
        $('#xb-aifanyi-page-info').text(`第 ${data.page} 页，共 ${data.total_pages} 页 (总计 ${data.total} 条记录)`);
        $('#xb-aifanyi-prev-page').prop('disabled', data.page <= 1);
        $('#xb-aifanyi-next-page').prop('disabled', data.page >= data.total_pages);
        $('#xb-aifanyi-logs-pagination').show();
    }
    
    // 删除记录
    function deleteLog(logId) {
        $.post(ajaxurl, {
            action: 'xb_aifanyi_delete_log',
            nonce: nonce,
            log_id: logId
        }, function(response) {
            if (response.success) {
                showMessage('记录删除成功', 'success');
                loadLogs(currentPage); // 重新加载当前页
            } else {
                showMessage('删除失败: ' + response.data, 'error');
            }
        });
    }
    
    // 清空所有记录
    function clearAllLogs() {
        const button = $('#xb-aifanyi-clear-all-logs');
        button.prop('disabled', true).text('清空中...');
        
        $.post(ajaxurl, {
            action: 'xb_aifanyi_clear_all_logs',
            nonce: nonce
        }, function(response) {
            if (response.success) {
                showMessage(response.data, 'success');
                // 清空显示区域
                $('#xb-aifanyi-logs-container').html('<p>暂无翻译记录</p>');
                $('#xb-aifanyi-logs-pagination').hide();
                currentPage = 1;
            } else {
                showMessage('清空失败: ' + response.data, 'error');
            }
        }).always(function() {
            button.prop('disabled', false).text('清空所有记录');
        });
    }
});
</script>