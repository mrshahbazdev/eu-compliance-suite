# Translations — EuroComply Omnibus

Drop compiled `eurocomply-omnibus-{locale}.mo` files here (e.g.
`eurocomply-omnibus-de_DE.mo`, `eurocomply-omnibus-fr_FR.mo`). The plugin
loads them on `init` via `load_plugin_textdomain()`.

POT generation (from the repo root):

```
wp i18n make-pot plugins/wp-omnibus-pricing plugins/wp-omnibus-pricing/languages/eurocomply-omnibus.pot
```
