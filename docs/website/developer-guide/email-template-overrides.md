# Email Template Overrides

The favourite emails are rendered from PHP templates you can override from your theme.

## Bundled templates

| File | Used for |
| --- | --- |
| `templates/emails/activity-favorited.php` | A favourited activity. |
| `templates/emails/comment-favorited.php` | A favourited comment. |
| `templates/emails/base.php` | Shared layout the two templates build on. |

## Overriding from a theme

Copy the template you want to change from the plugin's `templates/emails/` directory into this path in your active theme:

```
your-theme/buddypress/bp-favorite-notification/emails/activity-favorited.php
your-theme/buddypress/bp-favorite-notification/emails/comment-favorited.php
```

When a matching file exists in the theme (specifically the stylesheet, or child-theme, directory), the plugin loads it instead of its own. Nothing else is needed.

## Available variables and tokens

Templates are rendered with output buffering. The email tokens are extracted into local variables and are also replaced as `{token}` placeholders in the output. Available tokens:

- `{site_name}`, `{site_url}`
- `{user_name}` and `{favorited_by}` (the member who favourited)
- `{favorited_by_link}` (their profile URL)
- `{recipient_name}` (the author)
- `{activity_content}` (a trimmed excerpt)
- `{activity_link}` (permalink to the activity)
- `{settings_link}` (the author's notification settings)

The template file also has these colour variables available for inline styling: `$header_color`, `$accent_color`, `$button_color`, `$button_hover_color`, `$link_color`.

## Overriding the template path directly

Instead of a theme file, you can point the plugin at any path with the `bpfn_email_template_path` filter:

```php
add_filter( 'bpfn_email_template_path', function ( $path, $template ) {
    if ( 'emails/activity-favorited.php' === $template ) {
        return '/absolute/path/to/your-template.php';
    }
    return $path;
}, 10, 2 );
```

## Changing subjects, tokens, and headers

- Register or replace templates and subjects with `bpfn_email_templates`.
- Adjust the final subject and body with `bpfn_email_subject` and `bpfn_email_message`.
- Add headers with `bpfn_email_headers`.
- Change the sender with `bpfn_email_from_name` and `bpfn_email_from_email`.

See the [Hooks Reference](hooks-reference.md).

## Fallback

If no template file can be found at any location, the plugin sends a built-in inline HTML message so an email is always delivered.
