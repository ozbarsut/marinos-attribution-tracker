# Marinos Attribution Tracker

Bu eklenti su bilgileri toplar:

- Ziyaretci oturum baslangici (landing page)
- Kaynak/medium/campaign (UTM, gclid/gbraid/wbraid, fbclid, msclkid)
- Arama motoru alan adi ve eger mevcutsa arama kelimesi
- Sitede tiklanan link ve butonlar
- IP adresi ve user-agent

## Kurulum

1. `marinos-attribution-tracker` klasorunu `wp-content/plugins/` altina koyun.
2. WordPress admin > Eklentiler ekranindan eklentiyi aktif edin.
3. Sol menuden `Attribution Tracker` ekranina girip kayitlari izleyin.

## Onemli not (Google keyword)

Google cogu durumda organik arama kelimesini referrer'da gondermez. Bu nedenle:

- Organik trafikte keyword alani bos gelebilir.
- Google Ads tarafinda `gclid`, `gbraid`, `wbraid`, `gad_source` gibi parametreler tespit edilir.
- Keyword alaninda once `utm_term` aranir; yoksa `keyword`, `searchterm`, `search_term`, `term`, `query`, `q`, `utm_keyword` parametreleri denenir.
- Keyword takibini iyilestirmek icin kampanya URL'lerine mutlaka `utm_term` (veya yukaridaki esdeger bir parametre) ekleyin.
