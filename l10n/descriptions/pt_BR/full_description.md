# Aplicação GpxPod Nextcloud

Exiba, analise, compare e compartilhe arquivos de trilhas de GPS.

🌍 Ajude-nos a traduzir este aplicativo em [GpxPod Crowdin project](https://crowdin.com/project/gpxpod).

GpxPod :

* pode exibir arquivos gpx/kml/tcx/igc /fit em qualquer lugar em seus arquivos, arquivos compartilhados com você, arquivos em pastas compartilhadas com você. os arquivos fit serão convertidos e exibidos apenas se **GpsBabel** for encontrado no sistema do servidor
* 📏 suporta sistemas de medidas métricas, inglesas e náuticas
* 🗠 desenha gráfico interativo de elevação, velocidade ou ritmo
* 🗠 pode colorir as linhas da trilha por velocidade, elevação ou ritmo
* 🗠 mostrar estatísticas de trilha
* ⛛ filtrar trilhas por data, distância total...
* 🖻 exibe imagens com geo-tags encontradas no diretório selecionado
* 🖧 gera links públicos apontando para uma trilha/pasta. Este link pode ser usado se o arquivo/pasta for compartilhado por link público
* 🗁 permite que você mova os arquivos de trilha selecionados
* 🗠 pode corrigir elevações de trilhas se SRTM.py (gpxelevations) for encontrado no sistema do servidor
* ⚖ uma comparação global de várias trilhas
* ⚖ pode fazer comparação visual de pares de partes divergentes de faixas semelhantes
* 🀆 permite que os usuários adicionem servidores de mapas pessoais
* ⚙ salva/restaura os valores das opções do usuário
* 🖍 permite ao usuário definir manualmente as cores da linha de rastreamento
* 🕑 detecta o fuso horário do navegador
* 🗬 carrega símbolos de marcadores extras do GpxEdit se instalado
* 🔒 funciona com pasta de dados criptografados (criptografia do lado do servidor)
* 🍂 orgulhosamente usa o folheto com vários plug-ins para exibir o mapa
* 🖴 é compatível com bancos de dados SQLite, MySQL e PostgreSQL
* 🗁 adiciona a possibilidade de visualizar arquivos .gpx diretamente do aplicativo "Arquivos"

Este aplicativo foi testado no Nextcloud 15 com Firefox 57+ e Chromium.

Este aplicativo está em desenvolvimento (lento).

Link para o site do aplicativo Nextcloud: https://apps.nextcloud.com/apps/gpxpod

## Instalar

Veja o [AdminDoc](https://gitlab.com/eneiluj/gpxpod-oc/wikis/admindoc) para detalhes de instalação

## Problemas conhecidos

* má gestão de nomes de arquivos, incluindo aspas simples ou duplas
* *AVISO*, a conversão kml NÃO funcionará com arquivos kml recentes que usam a tag de extensão proprietária "gx: track".

Todos os comentários serão apreciados.