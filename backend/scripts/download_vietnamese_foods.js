const fs = require('fs');
const path = require('path');

const targetDir = path.resolve(__dirname, '../../frontend/assets/images/foods');
fs.mkdirSync(targetDir, { recursive: true });

const items = [
  { title: 'File:Com-Tam-2008.jpg', dest: 'com-tam-suon-bi-cha.jpg' },
  { title: 'File:Cơm Tấm, Da Nang, Vietnam.jpg', dest: 'com-tam-dac-biet.jpg' },
  { title: 'File:Cơm tấm SG (cơm sườn) ng27th9n2020 (1).jpg', dest: 'com-tam-suon-nuong.jpg' },
  { title: 'File:Thịt kho Tàu.jpg', dest: 'thit-kho-tau.jpg' },
  { title: 'File:Canh khổ qua.JPG', dest: 'canh-kho-qua.jpg' },
  { title: 'File:Canhchua2.jpg', dest: 'canh-chua.jpg' },
  { title: 'File:Cá kho tộ.JPG', dest: 'ca-kho-to.jpg' },
  { title: 'File:Pho Bo by Banh & Mee in Kirkgate Market.jpg', dest: 'pho-bo.jpg' },
  { title: 'File:Chicken Pho, Pho 21, Montparnasse, Paris 001.jpg', dest: 'pho-ga.jpg' },
  { title: 'File:Bun Bo Hue 1.jpg', dest: 'bun-bo-hue.jpg' },
  { title: 'File:Bún riêu cua nước.jpg', dest: 'bun-rieu.jpg' },
  { title: 'File:Bún thang.JPG', dest: 'bun-thang.jpg' },
  { title: 'File:Một phần bánh mì chảo với trứng và chả (38657217774).jpg', dest: 'banh-mi-chao.jpg' },
  { title: 'File:Bánh cuốn Thanh Trì.jpg', dest: 'banh-cuon.jpg' },
  { title: 'File:Banhbao.jpg', dest: 'banh-bao.jpg' },
  { title: 'File:Bánh giò.jpg', dest: 'banh-gio.jpg' },
  { title: 'File:Har gow (27871753892).jpg', dest: 'ha-cao.jpg' },
  { title: 'File:Xôi xéo.jpg', dest: 'xoi-xeo.jpg' },
  { title: 'File:Xôi gà.jpg', dest: 'xoi-ga.jpg' },
  { title: 'File:Xôi Gà Trứng Non Lạp Xướng (Sticky rice with chicken and eggs, and chinese sausages).jpg', dest: 'xoi-thap-cam.jpg' },
  { title: 'File:Pizzabanhtrangnuong1.jpg', dest: 'banh-trang-nuong.jpg' },
  { title: 'File:Cha gio.jpg', dest: 'nem-ran.jpg' },
  { title: 'File:Gỏi cuốn at a Vietnamese-style restaurant in Beijing (20180103175625).jpg', dest: 'goi-cuon.jpg' },
  { title: 'File:Ca Phe Sua Da.jpg', dest: 'ca-phe-sua-da.jpg' },
  { title: 'File:Sugarcane juice Flavours.jpg', dest: 'nuoc-mia.jpg' }
];

(async () => {
  for (const item of items) {
    try {
      const apiUrl = 'https://commons.wikimedia.org/w/api.php?action=query&format=json&titles=' + encodeURIComponent(item.title) + '&prop=imageinfo&iiprop=url&iiurlwidth=800';
      const res = await fetch(apiUrl, { headers: { 'User-Agent': 'FastFoodBot/1.0 (dev@fastfood.vn)' } });
      const data = await res.json();
      const page = Object.values(data?.query?.pages || {})[0];
      const imgUrl = page?.imageinfo?.[0]?.thumburl || page?.imageinfo?.[0]?.url;
      if (!imgUrl) {
        console.error('No url for', item.title);
        continue;
      }
      const imgRes = await fetch(imgUrl, { headers: { 'User-Agent': 'FastFoodBot/1.0 (dev@fastfood.vn)' } });
      if (!imgRes.ok) {
        console.error('Failed', imgUrl, imgRes.status);
        continue;
      }
      const buf = Buffer.from(await imgRes.arrayBuffer());
      fs.writeFileSync(path.join(targetDir, item.dest), buf);
      console.log('Saved', item.dest, '(', buf.length, 'bytes)');
    } catch (e) {
      console.error('Error on', item.title, e.message);
    }
  }
})();

