Issue #91 \Drupal\media_mpx\Plugin\Field\FieldFormatter\PlayerFormatter makes extending hard redux
 * https://patch-diff.githubusercontent.com/raw/Lullabot/media_mpx/pull/92.patch
BR-6856: Hack date conversion to map to unix timestamps instead of datetimes
 * See `23316172e425041af31ff9f378f3cfa54c1b843e`
Issue #108: Use mapped dates when calculating media availability
 * https://github.com/Lullabot/media_mpx/pull/108.diff
 * We also hack the patch to work with unix timestamps
Support ingesting FeedConfigs as entities #109
 * https://github.com/Lullabot/media_mpx/pull/109.diff
Fix not being able to import "0" strings and integers #110
 * https://github.com/Lullabot/media_mpx/pull/110.diff
Fix error on connection timeout during notification listen
* https://github.com/Lullabot/media_mpx/pull/116.diff
