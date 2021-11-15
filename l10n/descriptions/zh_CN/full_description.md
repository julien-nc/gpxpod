# GpxPod Nextcloud 应用程序

显示、分析、比较和共享 GPS 轨迹文件。

🌍 帮助我们在 [GpxPod Crowdin 项目](https://crowdin.com/project/gpxpod)上翻译这个应用程序。

GpxPod :

* 🗺 can display gpx/kml/tcx/igc/fit files anywhere in your files, files shared with you, files in folders shared with you. 只有在服务器系统上找到 **GpsBabel** 时，fit文件才会被转换和显示
* 📏 支持公制、英制和航海测量系统
* 🗠 draws elevation, speed or pace interactive chart
* 🗠 can color track lines by speed, elevation or pace
* 🗠 show track statistics
* ⛛ filter tracks by date, total distance...
* 🖻 displays geotagged pictures found in selected directory
* 🖧 generates public links pointing to a track/folder. 如果文件/文件夹被公共链接共享，此链接可以使用
* 🗁 允许您移动选中的轨迹文件
* 🗠 can correct tracks elevations if SRTM.py (gpxelevations) is found on the server's system
* ⚖ can make global comparison of multiple tracks
* ⚖ can make visual pair comparison of divergent parts of similar tracks
* 🀆 allows users to add personal map tile servers
* ⚙ 保存、恢复用户选项值
* 🖍 允许用户手动设置轨迹线颜色
* 🕑 检测浏览器时区
* 🗬 如果安装了 GpxEdit 会加载额外的标记符号
* 🔒 可以使用加密数据文件夹 (服务器端加密)
* 🍂 自豪地使用带有大量插件的 Leaflet 来显示地图
* 🖴 兼容 SQLite、MySQL 和 PostgreSQL 数据库
* 🗁 可以直接从“文件”应用中查看 .gpx 文件

这个应用程序在Nextcloud 15上使用 Firefox 57+ 和 Chromium 测试通过。

此应用正在（缓慢）开发中。

链接到Nextcloud应用程序网站： https://apps.nextcloud.com/apps/gpxpod

## 安装

请参阅 [AdminDoc](https://gitlab.com/eneiluj/gpxpod-oc/wikis/admindoc) 以了解安装详情

## 已知问题

* 包括单或双引号的文件名管理不佳
* *警告*，kml 转换将不适用于使用 "gx:track" 专用扩展标签的较新的 kml 文件。

如有任何反馈，将不胜感激。