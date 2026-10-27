#!/usr/bin/env python3
"""Local stdio MCP bridge. The only network path is OpenSSH to the VPS alias."""
from __future__ import annotations
import base64, json, os, re, shlex, subprocess, sys
from typing import Any
ALIAS=os.environ.get("ASODELTOL_ADMIN_SSH_ALIAS","asodeltol-vps-admin")
LIMIT=131072
TOOLS={"ssh_execute":{"command":{"type":"string"},"timeout_seconds":{"type":"integer"}},"ssh_read_file":{"path":{"type":"string"},"max_bytes":{"type":"integer"}},"ssh_write_file":{"path":{"type":"string"},"content_base64":{"type":"string"},"mode":{"type":"string"}},"ssh_upload_file":{"path":{"type":"string"},"content_base64":{"type":"string"},"mode":{"type":"string"}},"ssh_service":{"service":{"type":"string"},"action":{"type":"string"}},"ssh_session_status":{}}
PATH=re.compile(r"^/(?!.*(?:^|/)\.\.(?:/|$)).+")
SERVICE=re.compile(r"^[A-Za-z0-9@_.-]+$")
def call(command:str, timeout:int=60)->dict[str,Any]:
    remote="bash -lc "+shlex.quote(command)
    p=subprocess.run(["ssh","-T","-o","BatchMode=yes","-o","NumberOfPasswordPrompts=0","-o","StrictHostKeyChecking=yes","-o","ForwardAgent=no","-o","ClearAllForwardings=yes","-o",f"ConnectTimeout={min(timeout,20)}",ALIAS,remote],stdin=subprocess.DEVNULL,stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=timeout+10)
    return {"exit_code":p.returncode,"stdout":p.stdout.decode(errors="replace")[-LIMIT:],"stderr":p.stderr.decode(errors="replace")[-LIMIT:]}
def valid_path(value:Any)->str:
    if not isinstance(value,str) or not PATH.fullmatch(value): raise ValueError("invalid_absolute_path")
    return value
def execute(name:str,a:dict[str,Any])->dict[str,Any]:
    if name=="ssh_execute": return call(a["command"],int(a.get("timeout_seconds",60)))
    if name=="ssh_session_status": return call("id && hostname && pwd",20)
    if name in {"ssh_read_file"}:
        path=valid_path(a["path"]); limit=int(a.get("max_bytes",65536)); return call("head -c "+str(max(1,min(limit,LIMIT)))+" -- "+shlex.quote(path),30)
    if name in {"ssh_write_file","ssh_upload_file"}:
        path=valid_path(a["path"]); raw=base64.b64decode(a["content_base64"],validate=True)
        if len(raw)>LIMIT: raise ValueError("content_too_large")
        mode=a.get("mode","0640")
        if not isinstance(mode,str) or not re.fullmatch(r"0?[0-7]{3,4}",mode): raise ValueError("invalid_mode")
        encoded=base64.b64encode(raw).decode(); return call("umask 077; mkdir -p -- "+shlex.quote(os.path.dirname(path))+"; printf %s "+shlex.quote(encoded)+" | base64 -d > "+shlex.quote(path)+"; chmod "+shlex.quote(mode)+" -- "+shlex.quote(path),60)
    if name=="ssh_service":
        service=a["service"]; action=a["action"]
        if not isinstance(service,str) or not SERVICE.fullmatch(service) or action not in {"status","start","stop","restart","reload"}: raise ValueError("invalid_service_request")
        return call("sudo -n systemctl "+action+" -- "+shlex.quote(service),60)
    raise ValueError("unknown_tool")
def reply(i:Any,result:dict)->None: print(json.dumps({"jsonrpc":"2.0","id":i,"result":result},separators=(",",":")),flush=True)
for line in sys.stdin:
 try:
  q=json.loads(line); method=q.get("method"); i=q.get("id")
  if method=="notifications/initialized": continue
  if method=="initialize": reply(i,{"protocolVersion":"2025-06-18","capabilities":{"tools":{"listChanged":False}},"serverInfo":{"name":"asodeltol-vps-admin","version":"1.0.0"}})
  elif method=="tools/list": reply(i,{"tools":[{"name":n,"description":"SSH administrative operation", "inputSchema":{"type":"object","properties":{**s},"required":list(s),"additionalProperties":False},"annotations":{"readOnlyHint":n in {"ssh_read_file","ssh_session_status"},"destructiveHint":n not in {"ssh_read_file","ssh_session_status"},"openWorldHint":True}} for n,s in TOOLS.items()]})
  elif method=="tools/call":
   n=q.get("params",{}).get("name"); a=q.get("params",{}).get("arguments",{}); data=execute(n,a) if n in TOOLS and isinstance(a,dict) else (_ for _ in ()).throw(ValueError("invalid_tool")); reply(i,{"content":[{"type":"text","text":json.dumps(data)}],"structuredContent":data,"isError":data.get("exit_code",0)!=0})
  else: reply(i,{"error":{"code":-32601,"message":"method_not_found"}})
 except Exception as e: reply(locals().get("i"),{"content":[{"type":"text","text":str(e)}],"isError":True})
