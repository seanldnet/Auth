# 授权续费与到期提醒

## 续费接口

### POST /api/admin/licenses/{licenseId}/renew
管理员续费任意授权。

### POST /api/licenses/{licenseId}/renew
代理/下级代理续费本人签发的授权；不允许跨代理续费。

### POST /api/v1/licenses/{licenseId}/renew
使用 API Key 续费。代理 API Key 必须具有 licenses.renew 权限；管理员 API Key 使用全量 v1 scope。

请求 Header：
- Idempotency-Key：8–160 位唯一幂等键。

规则：
- 仅 ACTIVE 或 EXPIRED 授权可续费；FROZEN、VOID 不可续费。
- 使用授权当前套餐的时长配置；到期时间尚未到时从原到期时间续加，已过期时从当前 UTC 时间续加。
- DAY、MONTH、YEAR 按日历时长计算；月末日期会按目标月份最后一天截断。
- 永久授权无需续费。
- 软件的 renew_consumes_quota=1 时，代理/下级代理续费会扣减本人该软件/套餐 1 个额度；管理员操作不扣代理额度。
- 授权状态、到期时间、续费记录、授权事件和额度流水在同一事务中提交。
- 重复幂等键拒绝重复续期。

## 到期提醒

数据库迁移：
- 014_in_app_notifications.sql

通知范围：
- 授权签发人和已分配的授权持有人（如果存在）会收到站内通知。
- 到期前 7 天、3 天、1 天分别生成通知。
- 每个授权、到期时间和提醒天数使用唯一事件键，重复执行不会重复通知。

命令：
- php think auth:notify-expiring-licenses

建议每日 UTC 09:00 执行一次：
- 0 9 * * * cd /path/to/Auth/backend && php think auth:notify-expiring-licenses

站内接口：
- GET /api/notifications
- PATCH /api/notifications/{notificationId}/read
- PATCH /api/notifications/read-all

当前已实现站内通知；邮件、Telegram 和其他外部通知渠道仍待接入。
