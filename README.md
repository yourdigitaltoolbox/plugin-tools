# YDTB Wordpress Plugin Tools

  

A set of software to track private wordpress plugins for use with [php composer](https://getcomposer.org/).

I use [roots' Trellis](https://roots.io/trellis/) to deploy wordpress sites for my agency. The whole idea of keeping everything under version control really appeals to me. The only part that doesnt fit into the equation is paid wordpress plugins. And I use plenty of paid wordpress plugins. There are a few other people who have tried to tackle this same problem, and I have tried most of the solutions. Most notabally [SatisPress](https://github.com/cedaro/satispress). 
Satispress is a great stab at tracking paid plugins, but there were a few things that started breaking for me when I had more than a few plugins installed. (80+ plugins)

- I spooled up a separate wordpress instance and installed/activated all my plugins onto that one site. 
- When I did that then the site started slowing down a lot. And even though it wasnt a public site, It was slow enough on the admin side that I didnt want to get in there to use it. 
- Also some plugins don't play well together, and really don't work being installed on the same site. 
 - If I broke up the plugins into groups so that the site was faster, and so that plugins that were not compatable with each other were happy,  I now had to add multiple composer sources to my new projects to cover all of the plugins I wanted to track. 
 - Some plugins are only activated on a single production site (the client had a one-off plugin they purchased and wanted updated) & I didnt want to purchase a secondary licence to add it to satispress. And I did not want to host the plugin distribution on a production client site. 

For some of these reasons and more I decided to take my own stab at solving this issue. 

**Here are some of the requirements I wanted.** 

 1. **Distributed System:** The project would be broken up into a server component and (maybe) multiple client components.
	
 - The server will handle serving the single packages.json file for composer to consume. 
 - The client plugin will handle watching for plugin updates and then, when available, will notifiy the server that there is an update, and push that new plugin information to be stored remotely from the client
 
2. **Light Weight:** The client side should be able to be installed on multiple wordpress instances and not cause any noticable slowing. 

4. **Server should be cheap/free to run** Having multiple wordpress sites running just to manage updates can be an issue eventually. Ideally a single instance of something that I can just have on the internet is preferable. 

## Here's where I'm at so far. 

The [server component](https://github.com/ydtb-wp/ydtb-wp.github.io) is ALL handled via github & github actions. Storing all of the plugins in private repositories allows composer to authenticate with github natively all running inside the free tier of github a single github organization provides the space for everything to live. 

[The client](https://github.com/ydtb-wp/plugin-tools) authenticates with individual fine-grained access tokens to kick off the workflows that update the plugin repos. The client only needs access to dispatch a workflow, and so has minimal roles provided by github. 

Internally the server has a PAT with more authority & handles communicating with all of the plugin repos in the orginization and handling commiting, tagging, etc. 

Each time there is a plugin update then the information about that plugin is added to a database file located in the repo. By committing a change to this file then the github pages that host the composer package.json is regenerated, allowing automatic composer updates when plugins are pushed. 

## How to install the server actions (workflows)

1. Make a new github organization... for organizational purposes. (and so that github pages works correctly)
		In my example here we are going to use "my-new-org-abc" which is a very professional name ;)
		<details>
      <summary>Make New Org</summary>
          <div align="center">
        <img src="https://github.com/user-attachments/assets/0469dc3c-782e-4d2d-9477-325f98309529" width="400">
        <div>
    </details>

    We want our generated composer packages to live at "https://my-new-org-abc.github.io/packages.json" 
    to accomplish this we need to make a new repo in our org with the name my-new-org-abc.github.io
    This is a special repo because github will use it as the Site Pages repo. Check out this [github page](https://pages.github.com/) for more info on how this works. 
    <details>
      <summary>Make New Repo</summary>
      <div align="center">
      <img src="https://github.com/user-attachments/assets/189e2aec-1204-4da0-a8db-f1a2d9f0fe19" width=400>
      </div>
    </details>

2. Clone https://github.com/ydtb-wp/ydtb-wp.github.io  locally and replace the /data/database.json with an empty dataset. 

    ```
     {
    	 "plugins": {}
     }
    ```
    Then update the remote origin and push to your org repo just created. 



3. Make PAT Tokens so everything can autheticate and communicate. 
  	[Make Github Tokens](https://github.com/settings/tokens?type=beta)
      <details>
        <summary>Access Tokens</summary>
        <div align="center">
          <img src="https://github.com/user-attachments/assets/bcc6c5a3-fbdf-42fa-bf0b-b79c603388da" width=400>
        </div>
      </details>

    a. The Admin Token needs Read/Write access to:  Actions, Administration, Contents, Metadata, Pages
      <details>
        <summary>Admin Token</summary>
        <div align="center">
          <img src="https://github.com/user-attachments/assets/4691c6fc-c9f3-4245-b4ef-6e23ca764098" width=400>
        </div>
      </details>

    b. The Plugin Token needs Read/Write access to:  Actions
      <details>
        <summary>Plugin Token</summary>
        <div align="center">
          <img src="https://github.com/user-attachments/assets/7c2ccd70-9821-4112-9915-994183307260" width=400>  
        </div>
      </details>

4. Place the Admin token as a plugin secret named `ORG_ADMIN_TOKEN`
    	<details>
        <summary>Set Repo Secrets</summary>
        <div align="center">
          <img src="https://github.com/user-attachments/assets/905ee9fe-22e1-47de-8d3e-b8234dd3a075" width=400>
        </div>
      </details>

6. The plugin token is provided to the plugin via wp cli. 

## Installing the plugin

[The plugin](https://github.com/ydtb-wp/plugin-tools) is installed the same way most plugins are, you can either zip up the repo from github or composer install

After the plugin is activated you can run through the setup. 
Everything is setup using the WP CLI

`wp pt setToken <token>`
`wp pt setPluginUpdateURL <update url>`
`wp pt setPluginFetchURL <fetch url>`

then you can use `wp pt choose` to enter the wizard and select the plugins you want watched/pushed to the remote. 

A wordpress cron is started when the plugin is activated to check for plugin updates once every 15 minutes, 
if you want to manually check for updates you can use 

`wp pt checkUpgradeable` which will kick off the same process.
This will only check if there is a `plugin update available` **AND** when the server has an `older version` of the plugin stored. 
This means that if you simply install the current version of a plugin onto the site, the plugin version that will get pushed is the **NEXT** version that becomes available.  

If you want to push the current version you can use 
`wp pt pushSingle` this will take the contents of the plugin you select, zip it up, and send that to the server. It will check that the server has a previous version (or is not tracking the plugin) before pushing. 

**Plugin version being pushed can only get larger** Because everything is tracked in git the versioning needs to be linear. 
If you push version `1.0.0` you can push `1.1.0 or 1.1.1` but cannot **then** push `1.0.1` because these would be out of order. PluginTools will check the version of the singlePlugin you try to push, but you should be aware of this and add plugins linearly if you are pushing versions manually. 

## This is alpha software at the moment.

I am actively working on updating this, things might change or unexpected things might happen. It seems like most plugins work by pulling updates this way, but I'm sure some wont. I will continue to develop it as I find things to work on. 



## Contribution Status. 

If you have ideas on how to make this better I am open to hearing about it. I currently am using this in my own practice, and as such I have got it working for my orginization, I am open to learning how other people would be interested in using this. 

Open a discussion or an issue


