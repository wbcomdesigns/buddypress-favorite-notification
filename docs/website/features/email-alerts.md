# Email Alerts

Alongside the on-screen notification, the plugin emails the author when their activity or comment is favourited. The emails are standard BuddyPress emails, so you edit them in the same place as every other BuddyPress email.

## When the email is sent

The email is sent when a member favourites an activity or comment, on BuddyPress's `bp_activity_add_user_favorite` action. A member is never emailed for favouriting their own content.

An email does not depend on the web notification. If the author turned web notifications off but left email on, they still get the email. If they turned email off for that type, no email is sent. See [Member Notification Preferences](../usage/member-preferences.md).

## Editing the emails

Go to **Dashboard > Emails**. Two email types are registered:

- **Activity favourited** (`bpfn-activity-favorited`). Subject: `[{{{site.name}}}] {{favoriter.name}} favorited your update`.
- **Comment favourited** (`bpfn-comment-favorited`). Subject: `[{{{site.name}}}] {{favoriter.name}} favorited your comment`.

Edit the subject and body there like any other BuddyPress email. The messages use your site's BuddyPress email template and From settings.

The plugin creates the emails on install and upgrade if they are missing. It never overwrites an email you have edited. **BuddyPress > Tools > Reinstall emails** recreates them too.

## Tokens

These tokens work in the subject and body, along with BuddyPress's standard tokens such as `{{{site.name}}}`:

| Token | Value |
| --- | --- |
| `{{favoriter.name}}` | Name of the member who favourited. |
| `{{{favoriter.url}}}` | Link to that member's profile. |
| `{{activity.content}}` | A plain-text excerpt of the activity, 20 words. |
| `{{{activity.url}}}` | Link to the favourited activity. |

Developers can adjust the tokens with the `bpfn_email_tokens` filter. See the [Hooks Reference](../developer-guide/hooks-reference.md).

## Unsubscribe

Every email has a working unsubscribe link, using BuddyPress's own unsubscribe flow. Clicking it turns that email off for the member. The setting then shows as "No" on the member's **Settings > Email** tab.

## Sender name and address

The sender name and address come from your BuddyPress and WordPress email settings. The plugin has no separate sender setting.

## Delivery

Emails go through BuddyPress's email system and WordPress mail, so they use whatever mail configuration or SMTP plugin your site already has. The plugin does not send mail through any external service.
