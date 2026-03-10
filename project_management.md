# Project Management Documentation

# UA check
- student/index.php :file_path
  validate_user_agent() :using in it.
    student\validate-u-a.php :file_path

- get_simple_ua()
      student\get_simple_ua.php
      で、UAを簡略化して返す関数を作成する。
      例えば、"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
      というUAがあった場合、"Chrome" と返すようにする。

- interface RequestContent :name of interface
  getUserAgent(): string
  getIpAddress(): string
  getRequestUri(): string

  