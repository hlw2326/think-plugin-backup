# 数据库配置

数据库备份管理模块，纯 PHP 实现，无需 shell 命令，支持本地存储和远程对象存储（阿里云 OSS、七牛云、腾讯云 COS、又拍云 USS、Alist）同步上传。

> **作者微信**：hlw2326　|　**官网**：https://www.hlw2326.com

---

## 功能特性

- **纯 PHP 备份**：无需 shell/mysqldump，直接通过 PDO 导出数据库结构和数据
- **分批导出**：大表分批查询（每批 500 条），避免内存溢出
- **本地存储**：备份文件存储到服务器本地目录，支持自定义路径
- **远程同步**：备份成功后自动上传到对象存储（需在系统存储配置中完成云存储配置）
- **配置校验**：上传前自动检测对象存储配置是否完整，配置缺失时跳过上传不影响本地备份
- **远程删除**：删除本地备份时同步删除远程对象存储中的文件
- **孤表清理**：可清理数据库无记录但本地仍有文件的孤立备份

---

## 安装说明

执行 Phinx 迁移创建备份记录表：

```bash
php think migrate:run -p package/think-sql-data/stc
```

> 如果迁移文件已执行过，需手动补加新字段：
> ```sql
> ALTER TABLE `data_backup`
>   ADD COLUMN `storage_type` varchar(20) NOT NULL DEFAULT '' COMMENT '存储类型(空=本地,alioss/qiniu等=对象存储)' AFTER `status`,
>   ADD COLUMN `storage_url` varchar(500) NOT NULL DEFAULT '' COMMENT '对象存储访问地址' AFTER `storage_type`,
>   ADD INDEX `storage_type` (`storage_type`);
> ```

---

## 配置说明

### 备份存储路径

**配置路径**：`系统管理 → 数据库配置`

| 配置项 | 说明 |
|---|---|
| 备份存储路径 | 自定义备份文件存储目录，留空使用 `runtime/database`（常规）或 `public/runtime/database`（网站根目录可访问） |
| 远程对象存储 | 选择本地存储（不同步）或任一云存储类型。切换为云存储后，备份文件将自动同步上传到远程空间 |

> 远程对象存储需要先在 **系统管理 → 存储设置** 中完成对应云存储的配置（AccessKey、SecretKey、Bucket 等），否则备份时将跳过上传步骤。

### 配置 key 一览

| key | 说明 | 可选值 |
|---|---|---|
| `data.backup_path` | 备份文件本地存储路径 | 留空使用默认路径 |
| `data.backup_storage_type` | 远程对象存储类型 | `""`（本地），`alioss`，`qiniu`，`txcos`，`upyun`，`alist` |

---

## 数据库表结构

### data_backup 备份记录表

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | int | 主键ID |
| `name` | varchar(255) | 备份名称 |
| `file` | varchar(500) | 备份文件名（含时间戳） |
| `size` | bigint | 备份文件大小（字节） |
| `path` | varchar(500) | 备份文件完整路径 |
| `tables` | int | 备份表数量 |
| `status` | tinyint | 状态（0=失败，1=成功） |
| `storage_type` | varchar(20) | 存储类型（空=本地，`alioss`/`qiniu` 等=对象存储） |
| `storage_url` | varchar(500) | 对象存储访问地址（上传成功时填充） |
| `create_at` | timestamp | 创建时间 |
| `update_at` | timestamp | 更新时间 |

---

## 使用说明

### 创建备份

1. 进入 **数据管理 → 备份管理**
2. 点击 **创建备份** 按钮
3. 可填写备份名称（留空自动生成）
4. 点击 **开始备份**，完成后自动返回列表

### 还原备份

1. 在备份列表中点击对应记录的 **还原** 按钮
2. 确认后执行 SQL 还原（单文件限制 20MB）

### 远程对象存储

- 备份文件始终先保存到本地，再根据配置决定是否上传到云存储
- 上传前会自动检查云存储配置是否完整（Bucket、AccessKey、SecretKey 等），配置缺失时跳过上传，不影响本地备份
- 上传失败（如 key 被删除、网络异常）时 `storage_url` 为空，本地备份记录不受影响

### 清理孤立文件

点击 **清理孤立文件**，删除本地存在但数据库无对应记录的备份文件。

---
