"""Debug: print exact payload being sent to Kiro."""
import json, sys
sys.path.insert(0, '.')
from kiro_core import KiroFarmBot

with open('kiro_form/accounts.json', 'r') as f:
    accounts = json.load(f)

acc = accounts[0]
bot = KiroFarmBot(
    account_id=acc['account_id'],
    refresh_token=acc['refresh_token'],
    access_token=acc['access_token'],
    profile_arn=acc['profile_arn'],
    proxy=acc.get('proxy'),
)
bot.refresh_access_token()

msgs = [{"role": "user", "content": "say ok"}]
payload = bot._build_payload(msgs, "claude-sonnet-4")
print(json.dumps(payload, indent=2)[:2000])
