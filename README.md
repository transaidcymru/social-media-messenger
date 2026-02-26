# Social Media Messenger
Plugin for osTicket - Integrate osTicket with social media platforms!

Syncs Instagram DMs as ticket threads. Receive & send messages directly from osTicket, managing and assigning them as regular tickets!

<img width="600" height="600" alt="demo_screenshot_plugin" src="https://github.com/user-attachments/assets/b8be84fb-a999-4021-bc97-e3a6d35ebbff" />

### Features
- Receive Instagram direct messages in osTicket blazingly fast via Instagram API webhooks
- Reply to Instagram messages within osTicket itself
- Automatically sync messages on Instagram from a configured date (within the limits of the Instagram API)
- Handles messages sent by your Instagram account outside of osTicket seamlessly, syncing them with existing tickets
- Supports images!

### Limitations
- Most limitations are derived from the Instagram Messaging API itself; for instance certain format messages are unsupported.
- You must configure a verified Instagram business account + Facebook developer account to deploy the plugin.
- You must follow Facebook/Meta's terms of use (e.g. provide a public privacy policy) in order to use the Instagram API.
- We currently only support one Instagram account
- We only support Instagram at time of writing. Down the line we may consider adding other social media APIs. Or you could contribute support for other platforms!

## Install
TODO
tl;dr deploy the plugin on your osTicket instance as you would any other plugin; and setup a Facebook developer account + verified Instagram "professional" account, add a new application, and configure it alongside configuring the plugin on osTicket. You will need to enable some API permissions to gain access to messaging and webhooks. We recommend setting up a test user account for testing messaging.

## License
MIT

## Contributing
Contributions are welcome, especially improvements to the existing plugin features and adding support for services other than Instagram. However please bear in mind that we are a volunteer organisation who made this plugin for our own purposes primarily so maintenance of this project is ad-hoc; we are not paid for this work.

## Donations
We currently don't take donations directly for this project or the other software projects we do.
However, you can still help out our main work by donating to our mutual aid project for Trans, Nonbinary and Intersex people in Cymru (UK):
[https://donate.transaid.cymru](https://donate.transaid.cymru)
