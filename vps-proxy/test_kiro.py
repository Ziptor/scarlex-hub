import cbor2
import requests
import re
import json

class KiroFarmBot:
    def __init__(self, refresh_token, access_token, profile_arn):
        self.refresh_token = refresh_token
        self.access_token = access_token
        self.profile_arn = profile_arn
        
        self.session = requests.Session()
        self.csrf_token = None
        self.space_id = "beaf00ff-91de-49bd-a009-de8c690c639d"
        
        self.headers_base = {
            "accept": "application/cbor",
            "content-type": "application/cbor",
            "smithy-protocol": "rpc-v2-cbor",
            "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
            "origin": "https://app.kiro.dev"
        }

    def init_web_session(self):
        print("🌍 Заходим на сайт за CSRF-токеном...")
        self.session.cookies.set("RefreshToken", self.refresh_token, domain="app.kiro.dev")
        self.session.cookies.set("AccessToken", self.access_token, domain="app.kiro.dev")
        self.session.cookies.set("Idp", "Google", domain="app.kiro.dev")
        
        res = self.session.get("https://app.kiro.dev/")
        
        match = re.search(r'name=["\']csrf-token["\'][^>]*content=["\']([^"\']+)["\']', res.text, re.IGNORECASE)
        if not match:
            match = re.search(r'content=["\']([^"\']+)["\'][^>]*name=["\']csrf-token["\']', res.text, re.IGNORECASE)

        if match:
            self.csrf_token = match.group(1)
        else:
            print("❌ CSRF-токен не найден!")

    def update_tokens(self):
        if not self.csrf_token:
            self.init_web_session()

        print("🔄 Обновляем токены...")
        url = "https://app.kiro.dev/service/KiroWebPortalService/operation/GetToken"
        payload = {"profileArn": self.profile_arn}
        headers = self.headers_base.copy()
        headers["x-csrf-token"] = self.csrf_token
        
        try:
            response = self.session.post(url, data=cbor2.dumps(payload), headers=headers)
            if response.status_code == 200:
                if "AccessToken" in self.session.cookies:
                    self.access_token = self.session.cookies.get("AccessToken")
                return True
            return False
        except Exception:
            return False

    def send_message(self, prompt):
        # Если токенов нет или они протухли, обновляем
        if not self.update_tokens():
            print("❌ Не удалось обновить сессию")
            return

        print(f"\n🚀 Отправляем промпт: '{prompt}'")
        url = "https://app.kiro.dev/service/KiroWebPortalService/operation/StreamSendMessage"
        
        payload = {
            "spaceId": self.space_id,
            "contentBlocks": [{"text": {"text": prompt}}],
            "sessionId": self.space_id,
            "modelId": "claude-opus-4.7",
            "csrfToken": self.csrf_token,
            "profileArn": self.profile_arn
        }

        headers = self.headers_base.copy()
        headers["authorization"] = f"Bearer {self.access_token}"
        headers["x-csrf-token"] = self.csrf_token

        # stream=True нужен для чтения ответа в реальном времени!
        response = self.session.post(url, data=cbor2.dumps(payload), headers=headers, stream=True)
        
        if response.status_code == 200:
            print("✅ Ответ от нейросети:\n")
            print("-" * 40)
            
            for chunk in response.iter_content(chunk_size=None):
                if chunk:
                    # Ищем маркер того, что в этом куске есть текст
                    if b'"text"' in chunk:
                        try:
                            # Вырезаем JSON-кусок из бинарного мусора
                            match = re.search(b'(\{.*?"text".*?\})', chunk)
                            if match:
                                # Превращаем байты в нормальную строку и парсим JSON
                                json_str = match.group(1).decode('utf-8')
                                data = json.loads(json_str)
                                
                                # Выводим текст в реальном времени, как в ChatGPT
                                if "text" in data:
                                    print(data["text"], end="", flush=True)
                        except Exception:
                            pass
                            
            print("\n" + "-" * 40)
        else:
            print(f"❌ Ошибка отправки: {response.status_code}")

# ==========================================
# ЗАПУСК
# ==========================================
# Оставь тут те же токены, с которыми у тебя только что всё сработало
REFRESH = "aorAAAAAGp3i8g9ZKhB6b7TTuEIuc1_C7Ffw28somrm0TdWZjs0uUOzusIWFxoKAgFRwqsVzaLtE3-P1WQFoQIDpoBkc0:MGYCMQCv5zvm/tz4La60HvvfvY1mHp0G6r/0P3LEIh8SdWe65xNByfD6hvclycLRhw8OovECMQDgQFrvJUFqk7vMdg7g89F3kUNJPoAqu77RtRMYf5jJSmjLxBTsb7DB2ZEmPHOnt3g"
ACCESS = "aoaAAAAAGoCHMMwaAMy7eUw6m3VEy75AV-876tnUeXdNVeYFaDzbjRoNd05IYbcVSKo4jO0opUNssMYbph3jzSt9gBkc0:MGQCMB945iUJuqEE7/fbxVUf2vRMMbZn96Y5zG8/PtrJnigb6Z2IOtc/zIUPi7TxEARPaAIwTEFDhrmL/buVjlhYV20lpHlZ3SM+BBlhBVozEAv8Gm/jQevx6ayKrW1+MVvyHc1V"
ARN = "arn:aws:codewhisperer:us-east-1:699475941385:profile/EHGA3GRVQMUK"

bot = KiroFarmBot(refresh_token=REFRESH, access_token=ACCESS, profile_arn=ARN)

# Проверяем связь!
bot.send_message("Привет! Напиши 'работает', если ты меня слышишь.")