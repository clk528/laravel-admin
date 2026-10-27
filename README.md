# clk528/laravel-admin

基于 [z-song/laravel-admin](https://github.com/z-song/laravel-admin) 维护的后台管理组件，提供表格、表单、详情页、权限、菜单和文件上传等功能，可用少量 PHP 代码构建 CRUD 后台。

当前版本面向 **Laravel 13 / PHP 8.3+** 进行兼容升级，Composer 包名为 `clk528/laravel-admin`，PHP 命名空间仍为 `Encore\Admin`。本分支不再声明支持 Laravel 5–12。

## 环境与依赖

以下版本约束与本包的 `composer.json` 一致：

| 类型 | 依赖 | 版本约束 |
| --- | --- | --- |
| 运行环境 | PHP | `^8.3` |
| 运行依赖 | `laravel/framework` | `^13.0` |
| 运行依赖 | `symfony/css-selector` | `^7.4` 或 `^8.0` |
| 运行依赖 | `symfony/dom-crawler` | `^7.4` 或 `^8.0` |
| 开发依赖 | `fakerphp/faker` | `^1.24` |
| 开发依赖 | `maatwebsite/excel` | `^4.0` |
| 开发依赖 | `orchestra/testbench` | `^11.0` |
| 开发依赖 | `phpunit/phpunit` | `^11.5.50` 或 `^12.5.8` |

上传文件需要启用 PHP Fileinfo 扩展；测试需要 PDO SQLite，基础图片上传测试需要 GD。Composer 会根据 PHP 版本和宿主项目的依赖解析实际安装版本。

2026-10-27 本包目录的验证环境：PHP 8.5.12、Laravel 13.38.0、CSS Selector 8.1.6、DOM Crawler 8.1.5、Laravel Excel 4.0.6、Testbench 11.3.0、PHPUnit 12.5.42、Faker 1.24.1。这是本地验证记录，并非固定版本要求；宿主项目使用自己的依赖锁文件。

## 全新后台安装

在 Laravel 13 应用中安装本包并配置数据库连接和 `APP_KEY` 后，执行以下命令。以下命令假设 PHP 和 Composer 已加入 PATH。

```powershell
php artisan vendor:publish --provider="Encore\Admin\AdminServiceProvider"
php artisan admin:install
```

资源发布后可在 `config/admin.php` 调整后台路由前缀、目录、数据库表名、认证和上传配置。`admin:install` 会执行迁移，并在后台用户表为空时初始化管理员数据及后台目录。

使用 `php artisan serve` 启动应用后，默认后台入口为 `http://localhost:8000/admin`，初始账号和密码均为 `admin`，首次登录后应修改密码。

## 已有项目升级

已有后台在宿主项目目录同步依赖：

```powershell
composer update clk528/laravel-admin symfony/dom-crawler symfony/css-selector --with-dependencies --minimal-changes --no-scripts
php artisan package:discover
```

已有项目无需重新运行 `admin:install`，也无需因本次兼容升级重新发布配置或重新迁移业务数据库。

本次升级包括：

- 将数据库字段生成改为使用 Laravel 原生 `SchemaBuilder::getColumns()`，移除 `doctrine/dbal` 依赖。
- 适配现代 Symfony 响应头和 PJAX 处理，兼容 JSON、流式响应及文件响应。
- 将提示消息以数组形式写入会话，兼容 JSON 会话序列化和原有 `MessageBag` 数据。
- 修复 PHP 8.4/8.5 隐式可空参数、动态属性、空头像 URL 以及 CSV 转义参数等兼容问题。
- 适配 Laravel Excel 4，保留自定义导出器原有的 `protected $fileName` 配置方式。
- 增加 Laravel 13 集成测试及 PHP 8.3–8.5 CI 配置。

如果应用覆盖发布过 `admin::partials.toastr` 或 `admin::partials.alerts`，需将数组兼容处理合并到应用的覆盖视图。自定义 Excel 导出器若覆写 `query()`，需声明兼容的返回类型，详见 [Laravel 13 升级说明](docs/laravel13-upgrade.md)。

业务代码如果把 Collection 或模型对象写入缓存，还需检查宿主项目的 `cache.serializable_classes` 配置。禁止对象反序列化时，下拉框的 `id => 文本` 选项应缓存为普通数组，并迁移旧的对象缓存；此类业务缓存调整需要在宿主项目中完成。

## 可选功能与兼容范围

- **Excel 导出**：使用 `ExcelExporter` 时，宿主项目需安装 `maatwebsite/excel:^4.0`。本包的开发依赖不会自动安装到宿主项目。
- **图片处理**：基础图片上传不依赖 Intervention Image；`resize`、`thumbnail` 等高级处理仍使用 Intervention Image 2 接口，对应原有可选依赖 `~2.3`，尚未迁移或验证 Intervention Image 3/4。
- **排序扩展**：可选依赖为 `spatie/eloquent-sortable`，需根据宿主项目另行安装并验证。
- **前端资源**：AdminLTE、jQuery 及随包插件保留原有版本，本次没有升级前端组件，也未完成全面的浏览器交互回归。
- **第三方扩展**：原版 laravel-admin 扩展需逐个验证 Laravel 13 和 PHP 8.3+ 兼容性。

## 开发与测试

在本包目录安装开发依赖并运行测试：

```powershell
composer install
composer test
```

如需显示全部测试诊断信息：

```powershell
php vendor/phpunit/phpunit/phpunit --display-all-issues
```

默认测试套件位于 `tests/Compatibility`，使用 SQLite 内存数据库、array 会话和临时文件存储，不读取宿主项目的 `.env`。旧 BrowserKit 测试仅作为历史参考，不纳入默认套件。

测试覆盖类加载、管理表迁移、登录退出、用户/角色/权限/菜单页面、表单 CRUD 及关联更新、分页排序、PJAX、JSON 会话提示、文件和基础图片上传、XLSX 导出。本机验证使用 PHP 8.5.12；PHP 8.3/8.4 配有 CI 矩阵，本次未在本机执行。数据库驱动特定元数据使用固定样本测试，尚未完成各真实数据库驱动的完整兼容验证。

## 文档与来源

- [Laravel 13 兼容升级说明](docs/laravel13-upgrade.md)
- [原版中文文档](https://laravel-admin.org/docs/zh)：可参考组件用法，版本要求和安装说明以本 README 为准。
- [原版项目](https://github.com/z-song/laravel-admin)：感谢 z-song 及原项目贡献者。

## 许可证

本项目采用 [MIT 许可证](LICENSE)。
