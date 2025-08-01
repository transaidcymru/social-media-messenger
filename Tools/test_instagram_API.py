#!/usr/bin/env python3
import requests
import os
import json

API_KEY = os.environ["API_KEY"]
print(API_KEY)
headers = {
    "Authorization": f"Bearer {API_KEY}",
    "Content-Type": "application/json"
}


params = {
    "fields": "user_id,username"
}

r0 = requests.get("https://graph.instagram.com/v23.0/me/conversations",
             headers=headers, params={"fields": ""})


response = r0.json()
convo = response["data"][0]["id"]

r1 = requests.get(f"https://graph.instagram.com/v23.0/{convo}",
             headers=headers, params={"fields": "messages"})


message = r1.json()["messages"]["data"][0]["id"]

r2 = requests.get(f"https://graph.instagram.com/v23.0/{message}",
             headers=headers, params={"fields": "id,created_time,from,to,message"})

print(f"{message=}, {convo=}")
print(json.dumps(r2.json(), indent=4, ensure_ascii=False).encode("utf8").decode())