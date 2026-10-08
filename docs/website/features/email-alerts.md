# Email Alerts

Alongside the on-screen notification, the plugin can send the author an HTML email when their activity or comment is favourited.

## When the email is sent

The email is sent immediately after the BuddyPress notification is created, on the plugin's internal `bpfn_after_add_notification` action. This means an email is sent only when a notification is created, so the "no self-notification" rule and the member's preference both apply to email as well.

The email respects the recipient's "email" channel preference for the activity type. If the member has turned the email channel off, no email is sent even though the on-screen notification may still appear. See [Member Notification Preferences](../usage/member-preferences.md).

## Templates

There are two HTML templates, chosen by what was favourited:

- `templates/emails/activity-favorited.php` for a favourited activity.
- `templates/emails/comment-favorited.php` for a favourited comment.

Both extend a shared base template (`templates/emails/base.php`). If a template file cannot be found, the plugin falls back to a built-in inline HTML message so an email is always sent.

The subject lines are:

- Activity: `[{site_name}] {user_name} favorited your activity`
- Comment: `[{site_name}] {user_name} favorited your comment`

## Tokens

Templates and subject lines support these tokens, replaced at send time:

`{site_name}`, `{site_url}`, `{user_name}`, `{recipient_name}`, `{activity_content}`, `{activity_link}`, `{settings_link}`, `{favorited_by}`, `{favorited_by_link}`.

## Sender name and address

By default emails are sent from your site name and your site's admin email address (`admin_email`). There is no admin screen for this. Change them with the `bpfn_email_from_name` and `bpfn_email_from_email` filters. See the [Hooks Reference](../developer-guide/hooks-reference.md).

## Customising the templates

Copy the templates from the plugin's `templates/emails/` directory into a `buddypress/bp-favorite-notification/emails/` directory in your active theme, and your copies are used instead. See [Email Template Overrides](../developer-guide/email-template-overrides.md).

## Delivery

Emails are sent with WordPress's `wp_mail()`, so they go through whatever mail configuration or SMTP plugin your site already uses. The plugin does not send mail through any external service.
