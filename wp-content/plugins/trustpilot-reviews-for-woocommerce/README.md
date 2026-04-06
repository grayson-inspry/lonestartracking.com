# Trustpilot Reviews for WooCommerce

Display your Trustpilot reviews on your WooCommerce store using simple shortcodes.

## Features

- **Easy Setup**: Configure with your Trustpilot Business Unit ID or domain
- **Two Display Methods**: 
  - Custom styled reviews display
  - Official Trustpilot TrustBox widgets
- **Multiple Layouts**: List, grid, or carousel layouts
- **Caching**: Built-in caching to optimize performance
- **Responsive**: Mobile-friendly design
- **Customizable**: Control number of reviews, minimum star rating, and more

## Installation

1. Download the plugin files
2. Upload the `trustpilot-reviews-for-woocommerce` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to **Settings → Trustpilot Reviews** to configure

## Configuration

### Finding Your Business Unit ID

1. Log in to your [Trustpilot Business account](https://businessapp.b2b.trustpilot.com/)
2. Go to **Integrations** → **TrustBox Library**
3. Your Business Unit ID is in the widget code snippet

### Settings

| Setting | Description |
|---------|-------------|
| **Business Unit ID** | Your unique Trustpilot identifier |
| **Domain** | Your business domain (e.g., example.com) |
| **Cache Duration** | How often to refresh reviews (1-72 hours) |
| **Reviews Count** | Default number of reviews to display |

## Shortcodes

### Custom Reviews Display

```
[trustpilot_reviews]
```

**Parameters:**

| Parameter | Default | Description |
|-----------|---------|-------------|
| `count` | 5 | Number of reviews to show |
| `layout` | list | Display layout: `list`, `grid`, or `carousel` |
| `show_rating` | yes | Show overall TrustScore summary |
| `show_date` | yes | Show review dates |
| `min_stars` | 1 | Minimum star rating to display |

**Examples:**

```
[trustpilot_reviews count="3" layout="grid"]
[trustpilot_reviews count="10" min_stars="4" show_rating="no"]
[trustpilot_reviews layout="carousel" count="6"]
```

### Official TrustBox Widget

```
[trustpilot_widget]
```

**Parameters:**

| Parameter | Default | Description |
|-----------|---------|-------------|
| `type` | micro | Widget type: `micro`, `mini`, `slider`, `carousel`, `grid` |
| `theme` | light | Color theme: `light` or `dark` |
| `height` | 150px | Widget height |
| `width` | 100% | Widget width |

**Examples:**

```
[trustpilot_widget type="mini" theme="dark"]
[trustpilot_widget type="slider" height="300px"]
[trustpilot_widget type="carousel" width="800px"]
```

## Usage Examples

### On a Product Page
Add to your WooCommerce product template or use a shortcode block:
```
[trustpilot_reviews count="3" layout="list" show_rating="yes"]
```

### In the Footer
Display a compact widget across all pages:
```
[trustpilot_widget type="micro" theme="light"]
```

### Dedicated Reviews Page
Create a full reviews showcase:
```
[trustpilot_reviews count="20" layout="grid" min_stars="1"]
```

### Homepage Hero Section
Show your best reviews prominently:
```
[trustpilot_reviews count="3" min_stars="5" layout="carousel"]
```

## Styling

The plugin includes default styling, but you can customize it with CSS. Main classes:

```css
.trustpilot-reviews-container { }
.trustpilot-overall-rating { }
.trustpilot-stars { }
.trustpilot-review-item { }
.review-header { }
.review-title { }
.review-content { }
.review-footer { }
.review-author { }
.review-date { }
```

### Custom CSS Example

```css
/* Custom background color */
.trustpilot-reviews-container {
    background: #f5f5f5;
    border-radius: 12px;
}

/* Larger stars */
.trustpilot-stars .star {
    font-size: 1.5rem;
}

/* Custom review card styling */
.trustpilot-review-item {
    background: white;
    border: 2px solid #00b67a;
}
```

## Frequently Asked Questions

### Why aren't my reviews showing?

1. Check that your Business Unit ID or Domain is correct
2. Ensure your Trustpilot profile is public
3. Try clearing the cache from the settings page

### How often are reviews updated?

Reviews are cached based on your Cache Duration setting (default: 6 hours). You can manually refresh from the settings page.

### Can I filter reviews by star rating?

Yes! Use the `min_stars` parameter:
```
[trustpilot_reviews min_stars="4"]
```

### Is this plugin compatible with page builders?

Yes, the shortcodes work with Elementor, WPBakery, Gutenberg, and other page builders.

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- A Trustpilot business profile

## Changelog

### 1.0.0
- Initial release
- Custom reviews display with multiple layouts
- Official TrustBox widget integration
- Caching system
- Admin settings page

## Support

For support questions, please visit the plugin support forum or contact us at your-email@example.com.

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html
