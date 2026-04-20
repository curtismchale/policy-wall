Allows a site to restrict access to only those that have accepted a Policy that is defined by the site.

## Developer Docs

### Filters

#### `pw_bypass_caps`

By default we allow WordPress Administrators through the Policy accept blocker by checking `activate_plugins`. You can use `pw_bypass_caps` to add other capabilities through the blocker. The value is stored as an array so make sure you append to it and pass an array back via the filter output. You can override it by returning your own array which would remove `activate_plugins` as a capability that bypasses the check.

```php
add_filter('pw_bypass_caps', 'my_cap_bypass');
function my_cap_bypass( $caps ){
  $caps[] = 'new_capability';
  
  return $caps;
}
```
