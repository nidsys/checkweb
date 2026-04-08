
function randomUUID() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    const r = Math.random() * 16 | 0;
    const v = c === 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}
function getOrCreateTempKey() {
    let tempKey = localStorage.getItem('temp_key');
    
    if (!tempKey) {
        // 없으면 새로 생성
        tempKey = randomUUID(); // 예: "550e8400-e29b-41d4-a716-446655440000"
        localStorage.setItem('temp_key', tempKey);
    }
    
    return tempKey;
}
