# 授权管理 API 补充

所有以下管理接口仅管理员可调用，并通过会话鉴权与管理员权限中间件。

## 批量导入

POST /api/admin/software/{softwareId}/licenses/import

请求 JSON：
- plan_id：该软件下已启用的套餐 ID。
- license_keys：字符串数组，1–500 个，每个格式为 4 组、每组 8 位十六进制字符，中间使用短横线。

处理规则：
- 全批在一个事务中处理。
- 任一授权码格式错误、重复或已存在，整批回滚。
- 数据库只保存 SHA-256 摘要和授权提示，不保存授权码明文。
- 每个导入授权写入 LICENSE_IMPORTED 事件。

## CSV 导出

GET /api/admin/software/{softwareId}/licenses/export

支持 status、keyword 筛选，导出授权编号、授权提示、套餐、状态、设备、激活时间、到期时间和创建时间。

安全边界：授权码明文仅在生成/导入时由调用方持有，平台只保存摘要，因此导出文件不包含完整授权码明文。
