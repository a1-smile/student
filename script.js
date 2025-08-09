

    document.getElementById('commonForm').addEventListener('submit', function(event) {
        const idInput = document.getElementById('idInput');
        const idValue = idInput.value;
        const idPattern = /^[1-3][0-9]{3}$/;
        //  form 内の id="idError" p要素を取得
        const idError = document.getElementById('idError');
        //  idValue を trim() して空白を除去
        const idValueTrimmed = idValue.trim();

        if (!idPattern.test(idValueTrimmed)) {
            alert('正しいidを入力してください。');
            event.preventDefault(); // submitをキャンセル
            idInput.focus(); // 入力欄にフォーカスを当てる
            idError.textContent = '正しいidを入力してください。'; // エラーメッセージを表示
            return false;
        } else {
            idError.textContent = ''; // エラーメッセージをクリア
        }
    });

    
    document.getElementById('commonForm').addEventListener('submit', function(event) {
        
        const nameInput = document.getElementById('nameInput');
        
        const nameValue = nameInput.value.trim();

        // form 内の id="nameError" p要素を取得
        const nameError = document.getElementById('nameError');
        
        let hasError = false;
        
        
        
        // 名前のバリデーション
        if (!hasError && nameValue === '') {
            alert('名前を入力してください。');
            nameInput.focus();
            nameError.textContent = '名前を入力してください。';
            hasError = true;
        } else {
            nameError.textContent = ''; // エラーメッセージをクリア
        }
        
        // エラーがある場合は送信をキャンセル
        if (hasError) {
            event.preventDefault();
            return false;
        }
    });
