# 全局区域节点 API

仅管理员可访问，所有操作写入 `auth_audit_logs`。

## 数据库
先执行 `backend/database/schema/011_global_region_nodes.sql`。区域登记表只保存区域代码、名称、HTTPS API 基础地址、流量权重、启停状态和备注。

## 接口
- `GET /api/admin/regions`：列出区域节点。
- `POST /api/admin/regions`：创建区域节点。
- `PUT /api/admin/regions/{regionId}`：更新区域节点。

请求字段：
- `region_code`：2–32 位大写字母、数字、下划线或短横线，唯一。
- `region_name`：区域名称。
- `api_base_url`：无凭据、无查询参数的 HTTPS 基础地址。
- `traffic_weight`：0–10000。
- `status`：ACTIVE 或 DISABLED。
- `description`：可选备注。

## 重要边界
此模块是控制面区域节点登记和配置页面，不会自动执行跨区域流量调度、消息队列同步、状态复制、健康探测或故障切换。真实跨区域部署与压测必须另行实施和验收。
