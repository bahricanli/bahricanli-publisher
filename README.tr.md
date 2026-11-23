# BahriCanli Publisher

[English](README.md)

[![WordPress eklenti sürümü](https://img.shields.io/wordpress/plugin/v/bahricanli-publisher)](https://wordpress.org/plugins/bahricanli-publisher/)
[![Test edilen WordPress sürümü](https://img.shields.io/wordpress/plugin/tested/bahricanli-publisher)](https://wordpress.org/plugins/bahricanli-publisher/)
[![GitHub sürümü](https://img.shields.io/github/v/release/bahricanli/bahricanli-publisher)](https://github.com/bahricanli/bahricanli-publisher/releases/latest)
[![Testler](https://github.com/bahricanli/bahricanli-publisher/actions/workflows/tests.yml/badge.svg)](https://github.com/bahricanli/bahricanli-publisher/actions/workflows/tests.yml)
[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/bahricanli/bahricanli-publisher/badge)](https://scorecard.dev/viewer/?uri=github.com/bahricanli/bahricanli-publisher)
[![Lisans](https://img.shields.io/badge/license-GPL--3.0--or--later-blue.svg)](LICENSE)
[![GitHub Sponsors](https://img.shields.io/badge/Sponsor-GitHub%20Sponsors-ea4aaa?logo=githubsponsors)](https://github.com/sponsors/bmericc)

BahriCanli Publisher, content-manager.tr üzerinden WordPress yazı ve sayfalarının oluşturulmasını, güncellenmesini ve silinmesini sağlar. Eklenti, paylaşılan bir token ile kimlik doğrular. REST API ve alternatif AJAX uç noktaları sunar. [content-manager.tr](https://content-manager.tr) · [WordPress.org eklenti sayfası](https://wordpress.org/plugins/bahricanli-publisher/)

## Kurulum

1. Eklentiyi WordPress.org dizininden kurun veya `bahricanli-publisher` klasörünü `/wp-content/plugins/` dizinine yükleyin.
2. WordPress’te **Eklentiler** menüsünden etkinleştirin.
3. **Ayarlar → BahriCanli Publisher** sayfasında bir token oluşturun veya mevcut token’ı girin.
4. Aynı değeri content-manager.tr yapılandırmasında `WORDPRESS_PLUGIN_TOKEN` olarak kullanın.

## Sürüm ve geliştirme

WordPress.org dağıtımı için `1.12.1` gibi `v` öneki olmayan bir sürüm etiketi kullanılır. GitHub sürümünde kurulum ZIP’i ve Sigstore imzalı derleme kanıtı yer alır. ZIP’i `gh attestation verify bahricanli-publisher.zip --repo bahricanli/bahricanli-publisher` komutuyla doğrulayabilirsiniz. [Sürümler](https://github.com/bahricanli/bahricanli-publisher/releases) · [Değişiklik geçmişi](CHANGELOG.md) · [Katkı kılavuzu](CONTRIBUTING.md)

## Lisans

Eklenti `1.12.1` sürümünden itibaren GNU Genel Kamu Lisansı v3.0 veya sonrası (`GPL-3.0-or-later`) ile lisanslanır. [Lisans metni](LICENSE)
