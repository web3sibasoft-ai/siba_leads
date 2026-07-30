# Siba Leads Module

Perfex CRM module for advanced leads customizations without editing core files.

## Upstream Repository

- URL: `https://github.com/web3sibasoft-ai/siba-leads.git`

## Connect Local Module to Repo

Run these commands from the Perfex project root:

```bash
git init modules/siba_leads
git -C modules/siba_leads remote add origin https://github.com/web3sibasoft-ai/siba-leads.git
git -C modules/siba_leads add .
git -C modules/siba_leads commit -m "Initialize siba_leads module scaffold"
git -C modules/siba_leads branch -M main
git -C modules/siba_leads push -u origin main
```

If you prefer it as a submodule instead:

```bash
git submodule add https://github.com/web3sibasoft-ai/siba-leads.git modules/siba_leads
```
