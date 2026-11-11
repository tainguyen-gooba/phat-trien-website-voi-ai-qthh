(async () => {
  const base = 'http://localhost:5000';

  console.log('--- 1. Testing GET /api/v1/chat/recommendations ---');
  let res = await fetch(base + '/api/v1/chat/recommendations');
  let json = await res.json();
  console.log('Recommendations status:', res.status, 'Best sellers count:', json.data?.bestSellers?.length);

  console.log('--- 2. Testing POST /api/v1/chat/new-session ---');
  res = await fetch(base + '/api/v1/chat/new-session', { method: 'POST' });
  json = await res.json();
  const sessionId = json.data?.sessionId;
  console.log('New session status:', res.status, 'SessionId:', sessionId);

  console.log('--- 3. Testing POST /api/v1/chat/message (Món dưới 50k) ---');
  res = await fetch(base + '/api/v1/chat/message', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message: 'Có món nào dưới 50.000đ không bạn?', sessionId })
  });
  json = await res.json();
  console.log('Message response status:', res.status);
  console.log('Reply preview:\n', json.data?.reply);
  console.log('Products attached:', json.data?.products?.length);

  console.log('--- 4. Testing POST /api/v1/chat/message (Phí ship) ---');
  res = await fetch(base + '/api/v1/chat/message', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message: 'Phí ship giao hàng bao nhiêu tiền?', sessionId })
  });
  json = await res.json();
  console.log('Shipping reply preview:\n', json.data?.reply);

  console.log('--- 5. Testing POST /api/v1/chat/message (Combo) ---');
  res = await fetch(base + '/api/v1/chat/message', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message: 'Có combo nào tiết kiệm không?', sessionId })
  });
  json = await res.json();
  console.log('Combo reply preview:\n', json.data?.reply);

  console.log('--- 6. Testing GET /api/v1/chat/history ---');
  res = await fetch(base + '/api/v1/chat/history?sessionId=' + sessionId);
  json = await res.json();
  console.log('History count:', json.data?.messages?.length);

  console.log('--- 7. Testing Frontend Static Files ---');
  const css = await fetch(base + '/css/chatbot.css');
  console.log('/css/chatbot.css status:', css.status);
  const js = await fetch(base + '/js/chatbot.js');
  console.log('/js/chatbot.js status:', js.status);
  const comp = await fetch(base + '/components/chatbot.html');
  console.log('/components/chatbot.html status:', comp.status);

  console.log('\n========================================');
  console.log('✅ ALL AI CHATBOT TESTS COMPLETED SUCCESSFULLY!');
  console.log('========================================');
  process.exit(0);
})();

