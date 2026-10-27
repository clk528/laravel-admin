# Laravel 13 本地包兼容升级

此分支面向 PHP 8.3+、Laravel 13。包名仍为 `clk528/laravel-admin`，命名空间仍为 `Encore\Admin`。不再声明对 Laravel 5–12 的支持。

## 改动

- 数据库字段生成改用 Laravel 原生 `SchemaBuilder::getColumns()`，移除 Doctrine DBAL 依赖。保留模型连接、表前缀和字段类型映射；转义 PHP 默认值，保留 `0` / `false`，解析 MariaDB 的 SQL 字面量。
- PJAX 使用 Symfony 的响应头接口，保留没有异常对象的错误响应、JSON 和流式响应；移除弃用的 HTML 实体转换调用。
- 提示消息使用可 JSON 序列化的数组保存；视图同时接受数组和已有会话中的 `MessageBag`，无需更改全局会话序列化设置。
- 服务提供者合并默认配置；修复空头像 URL 检查、动态属性和隐式可空参数声明，CSV 写入显式指定转义字符。
- 可选 Excel 导出适配 `maatwebsite/excel:^4.0`。保留旧式 `protected $fileName` 子类配置，支持无列选择的导出、下载和存储。
- 增加 Testbench 11 / PHPUnit 11–12 测试和 PHP 8.3–8.5 CI；旧 BrowserKit 用例保留在原位置作为历史参考，不再纳入默认测试套件。

## 主项目依赖同步

本地主项目已使用 Composer path repository 和 junction，源代码修改会立即生效。包的依赖声明变化后还需在主项目执行一次：

```powershell
php D:\composer\composer.phar update clk528/laravel-admin symfony/dom-crawler symfony/css-selector --with-dependencies --minimal-changes --no-scripts
php artisan package:discover
```

不需要执行 `admin:install`、重新发布配置或重新迁移已有业务数据库。项目若覆盖发布过的 `admin::partials.toastr` / `admin::partials.alerts`，需将同样的数组兼容处理合并到覆盖视图。

## 测试

在本包目录执行：

```powershell
php D:\composer\composer.phar install
php vendor/phpunit/phpunit/phpunit --display-all-issues
```

测试只使用 SQLite 内存库、array 会话和临时文件存储，不读取 backend 的 `.env`。运行时将弃用警告转换为异常。

验证覆盖：包类加载，管理表迁移及初始数据，登录退出，用户/角色/权限/菜单/日志页面，表单增删改及关联更新，权限判断，分页排序，PJAX，JSON 会话提示，文件和基础图片上传，XLSX 导出。数据库驱动特定元数据使用固定样本验证；未连接实际 MySQL/MariaDB/PostgreSQL 服务。

本机验证环境为 PHP 8.5.12 / Laravel 13.38.0。PHP 8.3/8.4 的执行由新增 CI 矩阵覆盖，本次未在本机运行这两个版本。

主项目现有测试全部通过（16 项 / 28 条断言），247 条后台路由正常注册。本包的 `src`、`config`、`database` 及新测试套件共 312 个 PHP 文件通过语法与声明弃用检查。主项目仅更新依赖锁文件及 vendor 安装状态，其已有的 `composer.json` 和 `AppServiceProvider.php` 修改完整保留。

## 可选集成

- Excel 导出需要主项目自行安装 `maatwebsite/excel:^4.0`。自定义导出器若覆写 `query()`，返回类型应为 `Illuminate\Database\Query\Builder|Illuminate\Database\Eloquent\Builder|Illuminate\Database\Eloquent\Relations\Relation` 或其更窄的兼容类型。
- 基础图片上传不需要 Intervention Image。现有 `resize` / `thumbnail` 等高级处理仍采用 Intervention Image 2 的接口，需要单独安装原有可选依赖；本次未验证这些图像处理集成，也未迁移到 Intervention 3/4。
- 浏览器端旧版 AdminLTE、jQuery 及其插件未更换；测试验证 HTTP 渲染和数据流程，未做完整的浏览器交互回归。

框架变更参考：[Laravel 13 官方升级指南](https://laravel.com/framework/docs/13.x/upgrade)。
