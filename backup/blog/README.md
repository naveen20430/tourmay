# Blog pages backup

These files are archived copies of the public blog pages removed from the main navigation.

- `blog.php` — Blog listing page (was at `/blog`)
- `blog-post.php` — Single blog post page (was at `/blog-post/{slug}`)

The live site now uses **About Us** (`/about-us`) instead of Blog in the menu.

Admin blog management (`admin/blog.php`) and database tables are unchanged.

To restore the blog publicly, copy these files back to `public_html/` and update navigation in `includes/header.php` and `includes/footer.php`.
