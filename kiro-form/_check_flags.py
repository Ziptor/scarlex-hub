import cbor2, requests, re, json

with open('kiro_form/accounts.json', 'r') as f:
    accounts = json.load(f)

for acc in accounts[:1]:
    session = requests.Session()
    session.cookies.set('RefreshToken', acc['refresh_token'], domain='app.kiro.dev')
    session.cookies.set('AccessToken', acc['access_token'], domain='app.kiro.dev')
    session.cookies.set('Idp', acc.get('login_method', 'Google'), domain='app.kiro.dev')
    res = session.get('https://app.kiro.dev/', timeout=15)
    
    m = re.search(r"name=['\"]csrf-token['\"][^>]*content=['\"]([^'\"]+)['\"]", res.text, re.IGNORECASE)
    csrf = m.group(1)
    
    headers = {
        'accept': 'application/cbor', 'content-type': 'application/cbor',
        'smithy-protocol': 'rpc-v2-cbor', 'user-agent': 'Mozilla/5.0',
        'origin': 'https://app.kiro.dev', 'x-csrf-token': csrf,
    }
    
    r = session.post('https://app.kiro.dev/service/KiroWebPortalService/operation/GetToken',
        data=cbor2.dumps({'profileArn': acc['profile_arn']}), headers=headers, timeout=15)
    access = session.cookies.get('AccessToken')
    
    headers['authorization'] = f'Bearer {access}'
    r = session.post('https://app.kiro.dev/service/KiroWebPortalService/operation/GetUserInfo',
        data=cbor2.dumps({'profileArn': acc['profile_arn']}), headers=headers, timeout=15)
    info = cbor2.loads(r.content)
    
    print(f"Account: {acc['account_id']}")
    print(f"Email: {info.get('email')}")
    print(f"\nFeature flags:")
    for flag in info.get('featureFlags', []):
        print(f"  - {flag}")
    print(f"\nenableNewBilling: {'enableNewBilling' in info.get('featureFlags', [])}")
