document.getElementById('commonForm').addEventListener('submit', function(event) {
    const idInput = document.getElementById('idInput');
    const nameInput = document.getElementById('nameInput');
    const idError = document.getElementById('idError');
    const nameError = document.getElementById('nameError');
    
    const idValue = idInput.value.trim();
    const nameValue = nameInput.value.trim();
    const idPattern = /^[1-3][0-9]{3}$/;
    
    let hasError = false;
    let firstErrorField = null;
    
    // IDのバリデーション
    if (!idPattern.test(idValue)) {
        idError.textContent = '1~3で始まる4桁の数字を入力してください。';
        hasError = true;
        if (!firstErrorField) firstErrorField = idInput;
    } else {
        idError.textContent = '';
    }
    
    // 名前のバリデーション
    if (nameValue === '') {
        nameError.textContent = '名前を入力してください。';
        hasError = true;
        if (!firstErrorField) firstErrorField = nameInput;
    } else {
        nameError.textContent = '';
    }
    
    // エラーがある場合の処理
    if (hasError) {
        event.preventDefault();
        
        // 最初のエラーフィールドにフォーカス
        if (firstErrorField) {
            firstErrorField.focus();
        }
        
        // エラーサマリーを表示（1つのアラート）
        let errorMessages = [];
        if (idError.textContent) errorMessages.push('・' + idError.textContent);
        if (nameError.textContent) errorMessages.push('・' + nameError.textContent);
        
        alert('入力内容を確認してください。\n' + errorMessages.join('\n'));
        
        return false;
    }
});





    //  以下のコードでは、フォームの送信時に２つのエラーがある場合に
    //  両方のエラーメッセージを表示するようにしています。
    //  そして、フォーカスがあと勝ちになってしまいます。

    //  そのため、コメントアウトして、上のように修正します。

    // document.getElementById('commonForm').addEventListener('submit', function(event) {
    //     const idInput = document.getElementById('idInput');
    //     const idValue = idInput.value;
    //     const idPattern = /^[1-3][0-9]{3}$/;
    //     //  form 内の id="idError" p要素を取得
    //     const idError = document.getElementById('idError');
    //     //  idValue を trim() して空白を除去
    //     const idValueTrimmed = idValue.trim();

    //     if (!idPattern.test(idValueTrimmed)) {
    //         alert('1~3で始まる4桁の数字を入力してください。');
    //         event.preventDefault(); // submitをキャンセル
    //         idInput.focus(); // 入力欄にフォーカスを当てる
    //         idError.textContent = '1~3で始まる4桁の数字を入力してください。'; // エラーメッセージを表示
    //         return false;
    //     } else {
    //         idError.textContent = ''; // エラーメッセージをクリア
    //     }
    // });

    
    // document.getElementById('commonForm').addEventListener('submit', function(event) {
        
    //     const nameInput = document.getElementById('nameInput');
        
    //     const nameValue = nameInput.value.trim();

    //     // form 内の id="nameError" p要素を取得
    //     const nameError = document.getElementById('nameError');
        
    //     let hasError = false;
        
        
        
    //     // 名前のバリデーション
    //     if (!hasError && nameValue === '') {
    //         alert('名前を入力してください。');
    //         nameInput.focus();
    //         nameError.textContent = '名前を入力してください。';
    //         hasError = true;
    //     } else {
    //         nameError.textContent = ''; // エラーメッセージをクリア
    //     }
        
    //     // エラーがある場合は送信をキャンセル
    //     if (hasError) {
    //         event.preventDefault();
    //         return false;
    //     }
    // });
