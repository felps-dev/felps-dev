(function(){const t=document.createElement("link").relList;if(t&&t.supports&&t.supports("modulepreload"))return;for(const o of document.querySelectorAll('link[rel="modulepreload"]'))r(o);new MutationObserver(o=>{for(const s of o)if(s.type==="childList")for(const d of s.addedNodes)d.tagName==="LINK"&&d.rel==="modulepreload"&&r(d)}).observe(document,{childList:!0,subtree:!0});function a(o){const s={};return o.integrity&&(s.integrity=o.integrity),o.referrerPolicy&&(s.referrerPolicy=o.referrerPolicy),o.crossOrigin==="use-credentials"?s.credentials="include":o.crossOrigin==="anonymous"?s.credentials="omit":s.credentials="same-origin",s}function r(o){if(o.ep)return;o.ep=!0;const s=a(o);fetch(o.href,s)}})();function rt(e=location.hash){const[t,a=""]=e.replace(/^#/,"").split("?");return{caminho:t||"/",consulta:new URLSearchParams(a)}}function nt({rotas:e,naoEncontrada:t,saida:a,aoNavegar:r}){let o=null;function s(){const{caminho:d,consulta:v}=rt(),E=e.find(g=>g.padrao.test(d)),$={...E?d.match(E.padrao).groups:{},consulta:v},l=(E?E.tela($):null)??t(d);o?.(),a.innerHTML=String(l.render()),document.title=`${l.titulo} | Raiz Solidária`,o=l.montar?.(a)??null,r(d)}return window.addEventListener("hashchange",s),{iniciar:s}}const st=e=>{location.hash=e},ye="raiz-solidaria:v1:";function te(e,t){try{const a=localStorage.getItem(ye+e);return a===null?t:JSON.parse(a)}catch(a){return console.warn(`[armazenamento] valor inválido em "${e}"; usando o padrão.`,a),t}}function V(e,t){try{return localStorage.setItem(ye+e,JSON.stringify(t)),!0}catch(a){return console.warn(`[armazenamento] não foi possível gravar "${e}".`,a),!1}}function He(e){try{localStorage.removeItem(ye+e)}catch{}}const Se="raiz:dados",fe=e=>document.dispatchEvent(new CustomEvent(Se,{detail:{tipo:e}})),ze=e=>document.addEventListener(Se,e),it=e=>document.removeEventListener(Se,e),Be=e=>{const t=te(e,[]);return Array.isArray(t)?t:[]},ae=()=>Be("favoritos"),we=e=>ae().includes(e);function ct(e){const t=ae(),a=!t.includes(e);return V("favoritos",a?[...t,e]:t.filter(r=>r!==e)),fe("favoritos"),a}const Me=()=>Be("cadastros");function dt(e){const t={...e,id:crypto.randomUUID(),criadoEm:new Date().toISOString()};return V("cadastros",[t,...Me()]),fe("cadastros"),t}function lt(e){V("cadastros",Me().filter(t=>t.id!==e)),fe("cadastros")}function ut(){He("cadastros"),fe("cadastros")}const mt=()=>te("rascunho-cadastro",null),ft=e=>V("rascunho-cadastro",e),Ce=()=>He("rascunho-cadastro"),Ve=["normal","grande","maior"],pt=()=>{const e=te("tamanho-texto","normal");return Ve.includes(e)?e:"normal"},gt=e=>V("tamanho-texto",e),ht=Ve,vt=()=>te("filtro-projetos","todos"),bt=e=>V("filtro-projetos",e),We=["auto","claro","escuro","alto-contraste"],$t=()=>{const e=te("tema","auto");return We.includes(e)?e:"auto"},yt=e=>V("tema",e),St=We,Q=document.querySelector(".menu-toggle"),wt=document.getElementById("menu-principal");function ie(e){Q.setAttribute("aria-expanded",String(e)),Q.querySelector(".sr-only").textContent=e?"Fechar menu":"Abrir menu",wt.classList.toggle("is-open",e)}const be=()=>{for(const e of document.querySelectorAll('.submenu-toggle[aria-expanded="true"]'))e.setAttribute("aria-expanded","false")};function je(){const e=ae().length,t=document.querySelector("[data-contador-favoritos]");t.textContent=e,t.hidden=e===0,t.setAttribute("aria-label",`${e} projeto(s) favorito(s)`)}function _e(e){e==="auto"?delete document.documentElement.dataset.tema:document.documentElement.dataset.tema=e;for(const t of document.querySelectorAll('input[name="tema"]'))t.checked=t.value===e}function Oe(e){document.documentElement.dataset.tamanhoTexto=e;for(const t of document.querySelectorAll("[data-tamanho]"))t.setAttribute("aria-pressed",String(t.dataset.tamanho===e))}function Mt(e){const t="/"+(e.split("/")[1]??"");for(const a of document.querySelectorAll("#menu-principal a[href^='#/']")){const r=a.getAttribute("href").slice(1);r===e||r!=="/"&&r===t?a.setAttribute("aria-current","page"):a.removeAttribute("aria-current")}document.querySelector('[aria-controls="submenu-projetos"]').classList.toggle("is-current",t==="/projetos"),ie(!1),be()}function Et(){Q.addEventListener("click",()=>ie(Q.getAttribute("aria-expanded")!=="true")),window.matchMedia("(min-width: 48em)").addEventListener("change",e=>{e.matches&&ie(!1)}),document.querySelector(".site-header").addEventListener("click",e=>{const t=e.target.closest(".submenu-toggle");if(t){const r=t.getAttribute("aria-expanded")!=="true";be(),t.setAttribute("aria-expanded",String(r));return}const a=e.target.closest("[data-tamanho]")?.dataset.tamanho;ht.includes(a)&&(gt(a),Oe(a))}),document.querySelector(".site-header").addEventListener("change",e=>{e.target.name!=="tema"||!St.includes(e.target.value)||(yt(e.target.value),_e(e.target.value))}),document.addEventListener("click",e=>{e.target.closest(".has-submenu")||be()});for(const e of document.querySelectorAll(".has-submenu"))e.addEventListener("focusout",t=>{e.contains(t.relatedTarget)||e.querySelector(".submenu-toggle").setAttribute("aria-expanded","false")});document.addEventListener("keydown",e=>{if(e.key!=="Escape")return;const t=document.querySelector('.submenu-toggle[aria-expanded="true"]');t?(t.setAttribute("aria-expanded","false"),t.focus()):Q.getAttribute("aria-expanded")==="true"&&(ie(!1),Q.focus())}),ze(e=>{e.detail.tipo==="favoritos"&&je()}),_e($t()),Oe(pt()),je()}function Ae({titulo:e,mensagem:t,acao:a="Confirmar"}){const r=document.getElementById("dialogo-confirmar");return r.querySelector("#dialogo-confirmar-titulo").textContent=e,r.querySelector("[data-mensagem]").textContent=t,r.querySelector("[data-confirmar]").textContent=a,r.returnValue="",r.showModal(),new Promise(o=>{r.addEventListener("close",()=>o(r.returnValue==="confirmar"),{once:!0})})}function Dt(){for(const e of document.querySelectorAll("dialog.modal"))e.addEventListener("click",t=>{t.target===e&&e.close()})}const Lt={"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"},xt=e=>String(e).replace(/[&<>"']/g,t=>Lt[t]);class Ee{constructor(t){this.valor=t}toString(){return this.valor}}function Je(e){return e==null||e===!1?"":Array.isArray(e)?e.map(Je).join(""):e instanceof Ee?e.valor:xt(e)}function h(e,...t){return new Ee(e.reduce((a,r,o)=>a+r+(o<t.length?Je(t[o]):""),""))}function Ge(e,t){e.innerHTML=String(t)}const Ct=e=>new Ee(e),jt={480:new URL(""+new URL("horta-comunitaria-480-BW0Rzn2A.webp",import.meta.url).href,import.meta.url).href,768:new URL(""+new URL("horta-comunitaria-768-DvJCGivU.webp",import.meta.url).href,import.meta.url).href,1024:new URL(""+new URL("horta-comunitaria-1024-4qpes4fu.webp",import.meta.url).href,import.meta.url).href,1536:new URL(""+new URL("horta-comunitaria-1536-CZXIUSp2.webp",import.meta.url).href,import.meta.url).href},Te={480:new URL(""+new URL("horta-comunitaria-480-D90BzmYQ.jpg",import.meta.url).href,import.meta.url).href,768:new URL(""+new URL("horta-comunitaria-768-CewFOchu.jpg",import.meta.url).href,import.meta.url).href,1024:new URL(""+new URL("horta-comunitaria-1024-krsvA-R3.jpg",import.meta.url).href,import.meta.url).href,1536:new URL(""+new URL("horta-comunitaria-1536-Cp93gO1g.jpg",import.meta.url).href,import.meta.url).href},qe=e=>Object.entries(e).map(([t,a])=>`${a} ${t}w`).join(", "),Qe={webp:qe(jt),jpg:qe(Te),fallback:Te[1024],largura:1536,altura:1024},De={voluntariado:{rotulo:"Voluntariado",badge:"success"},doacoes:{rotulo:"Doações",badge:"info"}},B=[{slug:"horta",nome:"Horta do bairro",icone:"H",categoria:"voluntariado",resumo:"Cultivo coletivo, compostagem e educação alimentar.",descricao:"Canteiros comunitários onde vizinhos plantam, cuidam e dividem a colheita, com oficinas sobre alimentação saudável.",atividades:["Cuidado dos canteiros","Oficinas de cultivo","Partilha da colheita"],agenda:"Sábados, das 8h às 11h",vagas:12,status:{rotulo:"Inscrições abertas",badge:"success"},imagem:{...Qe,alt:"Voluntários trabalham lado a lado no cuidado de uma horta comunitária."}},{slug:"conexoes",nome:"Conexões digitais",icone:"C",categoria:"voluntariado",resumo:"Oficinas de tecnologia, currículo e segurança digital.",descricao:"Encontros para jovens e adultos aprenderem a usar computador e internet com autonomia e segurança.",atividades:["Computador e internet","Segurança e privacidade","Currículos e cursos"],agenda:"Terças e quintas, das 19h às 21h",vagas:4,status:{rotulo:"Vagas limitadas",badge:"warning"},imagem:null},{slug:"mesa",nome:"Mesa compartilhada",icone:"M",categoria:"doacoes",resumo:"Arrecadação de alimentos e itens essenciais.",descricao:"Campanhas de arrecadação, triagem e distribuição de alimentos não perecíveis para famílias do bairro.",atividades:["Alimentos não perecíveis","Triagem de itens","Campanhas comunitárias"],agenda:"Recebimento de segunda a sexta, das 9h às 17h",vagas:null,status:{rotulo:"Estoque baixo",badge:"error"},imagem:null}],pe=e=>B.find(t=>t.slug===e)??null,ue=(e,t)=>h`<span class="badge${t?` badge-${t}`:""}">${e}</span>`,Ze=(e,t)=>h`
  <button class="fav-btn" type="button" data-favoritar="${e.slug}" aria-pressed="${String(t)}">
    <span aria-hidden="true">${t?"♥":"♡"}</span><span class="sr-only">Favoritar ${e.nome}</span>
  </button>`;function Xe(e,t){const a=De[e.categoria];return h`
    <article class="card project-card col-md-6 col-lg-4" data-slug="${e.slug}">
      <div class="card-header">
        <span class="card-icon is-${e.categoria}" aria-hidden="true">${e.icone}</span>
        ${Ze(e,t)}
      </div>
      <div class="cluster">${ue(a.rotulo,a.badge)}${ue(e.status.rotulo,e.status.badge)}</div>
      <h3 class="card-title">${e.nome}</h3>
      <p>${e.resumo}</p>
      <div class="card-footer">
        <a class="card-link" href="#/projetos/${e.slug}">Ver detalhes<span class="sr-only"> de ${e.nome}</span></a>
      </div>
    </article>`}const _t=6e3;function me(e,t="success"){const a=document.querySelector(".toast-region"),o=document.getElementById("modelo-toast").content.firstElementChild.cloneNode(!0);o.classList.add(`toast-${t}`),o.querySelector("p").textContent=e,a.append(o);let s;const d=()=>{clearTimeout(s),o.classList.add("is-leaving"),o.addEventListener("animationend",()=>o.remove(),{once:!0})},v=()=>{s=setTimeout(d,_t)};o.querySelector(".toast-close").addEventListener("click",d),o.addEventListener("mouseenter",()=>clearTimeout(s)),o.addEventListener("mouseleave",v),v()}function Le(e,t){const a=r=>{const o=r.target.closest("[data-favoritar]");if(!o)return;const s=pe(o.dataset.favoritar),d=ct(s.slug);for(const v of e.querySelectorAll(`[data-favoritar="${s.slug}"]`))v.setAttribute("aria-pressed",String(d)),v.querySelector("[aria-hidden]").textContent=d?"♥":"♡";me(d?`${s.nome} adicionado aos favoritos.`:`${s.nome} removido dos favoritos.`,d?"success":"info"),t?.(s.slug,d)};return e.addEventListener("click",a),()=>e.removeEventListener("click",a)}const Ke=(e,{alt:t,sizes:a,prioridade:r=!1})=>h`
  <picture>
    <source type="image/webp" srcset="${e.webp}" sizes="${a}">
    <img src="${e.fallback}" srcset="${e.jpg}" sizes="${a}" alt="${t}" width="${e.largura}" height="${e.altura}"
      decoding="async" ${r?h`fetchpriority="high"`:h`loading="lazy"`}>
  </picture>`,Ot=()=>({titulo:"Cuidar começa perto",render:()=>{const e=B.reduce((t,a)=>t+(a.vagas??0),0);return h`
      <section class="hero" aria-labelledby="titulo-pagina">
        <div class="container grid hero-grid">
          <div class="hero-copy col-lg-6">
            <span class="badge badge-success">Inscrições abertas</span>
            <h1 id="titulo-pagina" tabindex="-1">Grandes mudanças. <em>Raízes locais.</em></h1>
            <p class="lead">Alimentação, educação e cuidado para fortalecer a comunidade, uma ação de cada vez.</p>
            <div class="button-row">
              <a class="btn btn-primary" href="#/projetos">Conheça nossos projetos</a>
              <a class="btn btn-secondary" href="#/cadastro">Quero participar</a>
            </div>
          </div>
          <figure class="hero-media col-lg-6">
            ${Ke(Qe,{alt:"Pessoas voluntárias cuidam juntas dos canteiros de uma horta comunitária.",sizes:"(min-width: 90em) 40rem, (min-width: 62em) 45vw, calc(100vw - 3rem)",prioridade:!0})}
            <figcaption>O futuro floresce quando a gente cultiva junto.</figcaption>
          </figure>
        </div>
      </section>
      <section class="section section-alt" aria-labelledby="missao">
        <div class="container grid">
          <div class="col-lg-5"><p class="eyebrow">Nossa missão</p><h2 id="missao">Cuidar começa perto.</h2></div>
          <div class="col-lg-7">
            <p class="lead">A Raiz Solidária é uma ONG fictícia deste projeto acadêmico, dedicada à segurança alimentar e à inclusão digital no bairro.</p>
            <ul class="stats">
              <li><strong>${B.length}</strong> frentes de atuação</li>
              <li><strong>${e}</strong> vagas de voluntariado abertas</li>
              <li><strong>1</strong> bairro como ponto de partida</li>
            </ul>
          </div>
        </div>
      </section>
      <section class="section" aria-labelledby="frentes">
        <div class="container">
          <div class="section-heading">
            <h2 id="frentes">Três formas de fazer a diferença.</h2>
            <a class="card-link" href="#/projetos">Ver todas as iniciativas</a>
          </div>
          <div class="grid card-grid" data-lista-projetos>${B.map(t=>Xe(t,we(t.slug)))}</div>
        </div>
      </section>`},montar:e=>Le(e)}),Re={todos:"Todos",...Object.fromEntries(Object.entries(De).map(([e,t])=>[e,t.rotulo])),favoritos:"Favoritos"},ke=e=>e.normalize("NFD").replace(/\p{Diacritic}/gu,"").toLowerCase();function At(e,{filtro:t,busca:a,favoritos:r}){const o=ke(a.trim());return e.filter(s=>(t==="todos"||(t==="favoritos"?r.includes(s.slug):s.categoria===t))&&(!o||ke(`${s.nome} ${s.resumo} ${s.atividades.join(" ")}`).includes(o)))}const Tt=()=>({titulo:"Projetos",render:()=>h`
    <section class="page-intro" aria-labelledby="titulo-pagina">
      <div class="container">
        <p class="eyebrow">Projetos</p>
        <h1 id="titulo-pagina" tabindex="-1">Pequenas ações. <em>Um bairro inteiro.</em></h1>
        <p class="lead">Filtre as frentes de atuação, favorite as que mais combinam com você e participe.</p>
      </div>
    </section>
    <section class="section" aria-labelledby="titulo-lista">
      <div class="container stack">
        <h2 id="titulo-lista" class="sr-only">Lista de projetos</h2>
        <form class="toolbar" data-filtros role="search" aria-label="Filtrar projetos">
          <div class="chips" role="group" aria-label="Categoria">
            ${Object.entries(Re).map(([e,t])=>h`<button class="chip" type="button" data-filtro="${e}" aria-pressed="false">${t}</button>`)}
          </div>
          <div class="field search-field">
            <label for="busca">Buscar</label>
            <input class="input" id="busca" name="busca" type="search" placeholder="Ex.: horta, currículo" autocomplete="off">
          </div>
        </form>
        <p class="muted" data-contagem aria-live="polite"></p>
        <div class="grid card-grid" data-lista-projetos></div>
      </div>
    </section>`,montar:e=>{const t={filtro:vt(),busca:""};t.filtro in Re||(t.filtro="todos");const a=e.querySelector("[data-lista-projetos]"),r=e.querySelector("[data-contagem]"),o=e.querySelector("[data-filtros]");function s(){const d=At(B,{...t,favoritos:ae()});for(const v of o.querySelectorAll("[data-filtro]"))v.setAttribute("aria-pressed",String(v.dataset.filtro===t.filtro));Ge(a,d.length?h`${d.map(v=>Xe(v,we(v.slug)))}`:h`<div class="empty-state col-12"><p><strong>Nenhum projeto encontrado.</strong></p><p class="muted">Tente outro termo ou escolha “Todos”.</p></div>`),r.textContent=`${d.length} de ${B.length} projeto(s) exibido(s).`}return o.addEventListener("click",d=>{const v=d.target.closest("[data-filtro]");v&&(t.filtro=v.dataset.filtro,bt(t.filtro),s())}),o.addEventListener("input",d=>{t.busca=d.target.value,s()}),o.addEventListener("submit",d=>d.preventDefault()),s(),Le(e,()=>{t.filtro==="favoritos"&&s()})}});function qt({slug:e}){const t=pe(e);if(!t)return null;const a=De[t.categoria],r=t.categoria==="doacoes"?"doador":"voluntario";return{titulo:t.nome,render:()=>h`
      <section class="section" aria-labelledby="titulo-pagina">
        <div class="container grid">
          <div class="col-lg-7 stack">
            <nav aria-label="Trilha"><a class="card-link back-link" href="#/projetos">Todos os projetos</a></nav>
            <div class="cluster">${ue(a.rotulo,a.badge)}${ue(t.status.rotulo,t.status.badge)}</div>
            <h1 id="titulo-pagina" tabindex="-1">${t.nome}</h1>
            <p class="lead">${t.descricao}</p>
            <h2 class="h4">O que acontece por lá</h2>
            <ul class="card-list">${t.atividades.map(o=>h`<li>${o}</li>`)}</ul>
            <dl class="details">
              <div><dt>Agenda</dt><dd>${t.agenda}</dd></div>
              <div><dt>Vagas</dt><dd>${t.vagas===null?"Sem limite: toda doação ajuda":`${t.vagas} disponíveis`}</dd></div>
            </dl>
            <div class="button-row">
              <a class="btn btn-primary" href="#/cadastro?tipo=${r}&projeto=${t.slug}">Participar deste projeto</a>
              ${Ze(t,we(t.slug))}
            </div>
          </div>
          ${t.imagem?h`
            <figure class="hero-media col-lg-5">
              ${Ke(t.imagem,{alt:t.imagem.alt,sizes:"(min-width: 90em) 34rem, (min-width: 62em) 38vw, calc(100vw - 3rem)",prioridade:!0})}
            </figure>`:h`
            <div class="info-panel project-placeholder col-lg-5" aria-hidden="true"><span class="card-icon is-${t.categoria}">${t.icone}</span></div>`}
        </div>
      </section>`,montar:o=>Le(o)}}const ee=e=>String(e).replace(/\D/g,""),Rt={cpf:e=>ee(e).slice(0,11).replace(/^(\d{3})(\d)/,"$1.$2").replace(/^(\d{3}\.\d{3})(\d)/,"$1.$2").replace(/(\d{3})(\d{1,2})$/,"$1-$2"),cep:e=>ee(e).slice(0,8).replace(/^(\d{5})(\d)/,"$1-$2"),telefone:e=>{const t=ee(e).slice(0,11);if(t.length<=2)return t;const a=t.slice(2),r=t.length>10?5:4;return`(${t.slice(0,2)}) ${a.slice(0,r)}${a.length>r?"-"+a.slice(r):""}`}};function kt(e){const t=ee(e);if(t.length!==11||/^(\d)\1{10}$/.test(t))return!1;const a=r=>{const s=[...t.slice(0,r)].reduce((d,v,E)=>d+Number(v)*(r+1-E),0)*10%11;return s===10?0:s};return a(9)===Number(t[9])&&a(10)===Number(t[10])}function se(e){const t=/^(\d{4})-(\d{2})-(\d{2})$/.exec(e);if(!t)return null;const[a,r,o]=t.slice(1).map(Number),s=new Date(a,r-1,o);return s.getFullYear()===a&&s.getMonth()===r-1&&s.getDate()===o?s:null}function Pe(e,t){let a=t.getFullYear()-e.getFullYear();return t.getMonth()<e.getMonth()||t.getMonth()===e.getMonth()&&t.getDate()<e.getDate()?a-1:a}const Pt=/^[\p{L}' -]+$/u,Ft=/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/,It=/^\d{3}\.\d{3}\.\d{3}-\d{2}$/,Nt=/^\([1-9]\d\) (?:9\d{4}|[2-8]\d{3})-\d{4}$/,Yt=/^\d{5}-\d{3}$/,Fe=16,k=e=>[t=>String(t??"").trim()!=="",e],et={nome:[k("Informe seu nome completo."),[e=>Pt.test(e.trim()),"Use apenas letras, espaços, apóstrofo ou hífen."],[e=>e.trim().split(/\s+/).length>=2,"Informe nome e sobrenome."]],email:[k("Informe seu e-mail."),[e=>Ft.test(e.trim()),"Informe um e-mail válido, como nome@exemplo.com."]],nascimento:[k("Informe sua data de nascimento."),[e=>se(e)!==null,"Informe uma data válida."],[(e,t)=>se(e)<=t,"A data não pode estar no futuro."],[(e,t)=>Pe(se(e),t)>=Fe,`É preciso ter ${Fe} anos ou mais para participar.`],[(e,t)=>Pe(se(e),t)<=120,"Confira o ano de nascimento."]],cpf:[k("Informe seu CPF."),[e=>It.test(e),"Complete os 11 dígitos do CPF."],[kt,"CPF inválido: confira os dígitos."]],telefone:[k("Informe um telefone com DDD."),[e=>Nt.test(e),"Use DDD + 8 dígitos (fixo) ou 9 dígitos começando com 9 (celular)."]],cep:[k("Informe o CEP."),[e=>Yt.test(e),"Complete os 8 dígitos do CEP."]],cidade:[k("Informe a cidade."),[e=>e.trim().length>=2,"Informe o nome completo da cidade."]],estado:[k("Selecione a UF.")],tipo:[k("Escolha como deseja participar.")],mensagem:[[e=>e.length<=500,"Use no máximo 500 caracteres."]],ciente:[[e=>e===!0,"Confirme que usou dados fictícios."]]};function tt(e,t,a=new Date){for(const[r,o]of et[e]??[])if(!r(t,a))return o;return null}function Ut(e,t=new Date){const a={};for(const r of Object.keys(et)){const o=tt(r,e[r]??"",t);o&&(a[r]=o)}return a}const Ht="AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO".split(" "),zt=["cpf"],q=({id:e,rotulo:t,controle:a,dica:r="",largura:o="col-sm-6",opcional:s=!1})=>h`
  <div class="field ${o}">
    <label for="${e}">${t}${s?h` <span class="optional">(opcional)</span>`:""}</label>
    ${a}
    ${r?h`<p id="${e}-dica" class="field-hint">${r}</p>`:""}
    <p id="${e}-erro" class="field-error" hidden></p>
  </div>`,Bt=(e,t)=>`${t?`${e}-dica `:""}${e}-erro`,H=(e,t,a)=>h`<input class="input" id="${e}" name="${e}" aria-describedby="${Bt(e,a)}" ${t}>`,z=Ct;function he(e){const t=Object.fromEntries(new FormData(e));return t.ciente=e.elements.ciente.checked,t}function ve(e,t,a){const r=e.elements[t],o=e.querySelector(`#${t}-erro`);r.classList.toggle("is-invalid",!!a),r.classList.toggle("is-valid",!a&&r.type!=="checkbox"&&r.value!==""),r.setAttribute("aria-invalid",String(!!a)),o.textContent=a??"",o.hidden=!a}const Vt=({consulta:e})=>({titulo:"Cadastro",render:()=>h`
    <section class="section" aria-labelledby="titulo-pagina">
      <div class="container grid">
        <div class="col-lg-5">
          <div class="form-intro stack">
            <p class="eyebrow">Cadastro</p>
            <h1 id="titulo-pagina" tabindex="-1">Sua vontade de ajudar <em>tem lugar.</em></h1>
            <p class="lead">Os campos são conferidos enquanto você preenche. O rascunho fica salvo neste navegador.</p>
            <div class="alert alert-warning">
              <span class="alert-icon" aria-hidden="true">!</span>
              <div class="alert-body"><strong class="alert-title">Cadastro demonstrativo</strong><p>Use dados fictícios. Tudo fica apenas neste navegador; nada é enviado.</p></div>
            </div>
          </div>
        </div>
        <form id="cadastro" class="form-card col-lg-7" novalidate>
          <div class="alert alert-info" data-aviso-rascunho hidden>
            <span class="alert-icon" aria-hidden="true">i</span>
            <div class="alert-body"><strong class="alert-title">Rascunho restaurado</strong><p>Recuperamos o que você tinha preenchido (exceto o CPF).</p></div>
            <button class="btn btn-secondary btn-sm" type="button" data-descartar-rascunho>Descartar</button>
          </div>
          <div id="resumo-erros" class="alert alert-error" role="alert" tabindex="-1" hidden>
            <span class="alert-icon" aria-hidden="true">!</span>
            <div class="alert-body"><strong class="alert-title" data-resumo-titulo></strong><ul class="error-links" data-resumo-lista></ul></div>
          </div>
          <fieldset>
            <legend><span class="step-number" aria-hidden="true">1</span> Dados pessoais</legend>
            <div class="form-grid">
              ${q({id:"nome",rotulo:"Nome completo",largura:"",controle:H("nome",z('type="text" autocomplete="name" maxlength="100" required'))})}
              ${q({id:"email",rotulo:"E-mail",controle:H("email",z('type="email" autocomplete="email" maxlength="150" placeholder="nome@exemplo.com" required'))})}
              ${q({id:"nascimento",rotulo:"Data de nascimento",controle:H("nascimento",z('type="date" autocomplete="bday" required'),!0),dica:"É preciso ter 16 anos ou mais."})}
              ${q({id:"cpf",rotulo:"CPF",controle:H("cpf",z('type="text" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" data-mascara="cpf" required'),!0),dica:"Os dígitos verificadores são conferidos."})}
              ${q({id:"telefone",rotulo:"Telefone com DDD",controle:H("telefone",z('type="tel" autocomplete="tel" inputmode="tel" maxlength="15" placeholder="(11) 91234-5678" data-mascara="telefone" required'))})}
            </div>
          </fieldset>
          <fieldset>
            <legend><span class="step-number" aria-hidden="true">2</span> Endereço</legend>
            <div class="form-grid">
              ${q({id:"cep",rotulo:"CEP",controle:H("cep",z('type="text" autocomplete="postal-code" inputmode="numeric" maxlength="9" placeholder="00000-000" data-mascara="cep" required'))})}
              ${q({id:"cidade",rotulo:"Cidade",controle:H("cidade",z('type="text" autocomplete="address-level2" maxlength="80" required'))})}
              ${q({id:"estado",rotulo:"Estado",controle:h`<select class="input" id="estado" name="estado" autocomplete="address-level1" aria-describedby="estado-erro" required><option value="">Selecione a UF</option>${Ht.map(t=>h`<option>${t}</option>`)}</select>`})}
            </div>
          </fieldset>
          <fieldset>
            <legend><span class="step-number" aria-hidden="true">3</span> Como quer participar?</legend>
            <div class="form-grid">
              ${q({id:"tipo",rotulo:"Tipo de participação",controle:h`<select class="input" id="tipo" name="tipo" aria-describedby="tipo-erro" required><option value="">Selecione</option><option value="voluntario">Voluntário</option><option value="doador">Doador</option><option value="ambos">Ambos</option></select>`})}
              ${q({id:"projeto",rotulo:"Projeto de interesse",opcional:!0,controle:h`<select class="input" id="projeto" name="projeto" aria-describedby="projeto-erro"><option value="">Qualquer projeto</option>${B.map(t=>h`<option value="${t.slug}">${t.nome}</option>`)}</select>`})}
              ${q({id:"mensagem",rotulo:"Mensagem",opcional:!0,largura:"",dica:"Até 500 caracteres.",controle:h`<textarea class="input" id="mensagem" name="mensagem" rows="4" maxlength="500" aria-describedby="mensagem-dica mensagem-erro"></textarea>`})}
              <div class="field">
                <label class="check"><input id="ciente" name="ciente" type="checkbox" aria-describedby="ciente-erro" required> Estou usando dados fictícios nesta demonstração.</label>
                <p id="ciente-erro" class="field-error" hidden></p>
              </div>
            </div>
          </fieldset>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit">Salvar cadastro</button>
            <button class="btn btn-secondary" type="reset">Limpar campos</button>
          </div>
        </form>
      </div>
    </section>`,montar:t=>{const a=t.querySelector("#cadastro"),r=a.querySelector("[data-aviso-rascunho]"),o=a.querySelector("#resumo-erros"),s=new Set;let d;const v=mt();if(v){for(const[l,g]of Object.entries(v)){const b=a.elements[l];b&&(b.type==="checkbox"?b.checked=g===!0:b.value=g)}r.hidden=!1}for(const l of["tipo","projeto"]){const g=e.get(l),b=a.elements[l];g&&!b.value&&[...b.options].some(x=>x.value===g)&&(b.value=g)}function E(){clearTimeout(d),d=setTimeout(()=>{const l=he(a);for(const g of zt)delete l[g];ft(l)},400)}const $=l=>{const g=he(a),b=tt(l,g[l]??"");return ve(a,l,b),b};a.addEventListener("input",l=>{const g=l.target,b=Rt[g.dataset.mascara];b&&(g.value=b(g.value)),s.has(g.name)&&$(g.name),E()});let M=!1;return a.addEventListener("pointerdown",l=>{M=!!l.target.closest('[type="submit"]')}),a.addEventListener("change",l=>{s.add(l.target.name),M||$(l.target.name)}),a.addEventListener("focusout",l=>{const g=l.target.name;M||l.relatedTarget?.type==="submit"||!g||!(g in a.elements)||l.target.value===""||(s.add(g),$(g))}),a.addEventListener("submit",l=>{l.preventDefault(),M=!1;const g=he(a),b=Ut(g);for(const A of Object.keys(g))s.add(A),ve(a,A,b[A]);const x=Object.keys(b);if(x.length){o.querySelector("[data-resumo-titulo]").textContent=x.length===1?"1 campo precisa de atenção:":`${x.length} campos precisam de atenção:`,o.querySelector("[data-resumo-lista]").innerHTML=String(h`${x.map(A=>h`<li><a href="#${A}" data-ir-para="${A}">${b[A]}</a></li>`)}`),o.hidden=!1,o.focus();return}o.hidden=!0;const{cpf:R,ciente:W,...F}=g;dt({...F,cpfFinal:ee(R).slice(-2)}),clearTimeout(d),Ce(),me(`Cadastro de ${g.nome.split(" ")[0]} salvo neste navegador.`),st("#/painel")}),a.addEventListener("reset",()=>{setTimeout(()=>{s.clear();for(const l of a.elements)l.name&&ve(a,l.name,null);o.hidden=!0,r.hidden=!0,Ce()})}),a.addEventListener("click",l=>{const g=l.target.closest("[data-ir-para]");g&&(l.preventDefault(),a.elements[g.dataset.irPara].focus()),l.target.closest("[data-descartar-rascunho]")&&a.reset()}),()=>clearTimeout(d)}});function at(e){return e&&e.__esModule&&Object.prototype.hasOwnProperty.call(e,"default")?e.default:e}var ce={exports:{}},Wt=ce.exports,Ie;function ot(){return Ie||(Ie=1,(function(e,t){(function(a,r){e.exports=r()})(Wt,(function(){var a=1e3,r=6e4,o=36e5,s="millisecond",d="second",v="minute",E="hour",$="day",M="week",l="month",g="quarter",b="year",x="date",R="Invalid Date",W=/^(\d{4})[-/]?(\d{1,2})?[-/]?(\d{0,2})[Tt\s]*(\d{1,2})?:?(\d{1,2})?:?(\d{1,2})?[.:]?(\d+)?$/,F=/\[([^\]]+)]|Y{1,4}|M{1,4}|D{1,2}|d{1,4}|H{1,2}|h{1,2}|a|A|m{1,2}|s{1,2}|Z{1,2}|SSS/g,A={name:"en",weekdays:"Sunday_Monday_Tuesday_Wednesday_Thursday_Friday_Saturday".split("_"),months:"January_February_March_April_May_June_July_August_September_October_November_December".split("_"),ordinal:function(m){var c=["th","st","nd","rd"],n=m%100;return"["+m+(c[(n-20)%10]||c[n]||c[0])+"]"}},Z=function(m,c,n){var u=String(m);return!u||u.length>=c?m:""+Array(c+1-u.length).join(n)+m},I={s:Z,z:function(m){var c=-m.utcOffset(),n=Math.abs(c),u=Math.floor(n/60),i=n%60;return(c<=0?"+":"-")+Z(u,2,"0")+":"+Z(i,2,"0")},m:function m(c,n){if(c.date()<n.date())return-m(n,c);var u=12*(n.year()-c.year())+(n.month()-c.month()),i=c.clone().add(u,l),f=n-i<0,p=c.clone().add(u+(f?-1:1),l);return+(-(u+(n-i)/(f?i-p:p-i))||0)},a:function(m){return m<0?Math.ceil(m)||0:Math.floor(m)},p:function(m){return{M:l,y:b,w:M,d:$,D:x,h:E,m:v,s:d,ms:s,Q:g}[m]||String(m||"").toLowerCase().replace(/s$/,"")},u:function(m){return m===void 0}},j="en",_={};_[j]=A;var J="$isDayjsObject",N=function(m){return m instanceof re||!(!m||!m[J])},oe=function m(c,n,u){var i;if(!c)return j;if(typeof c=="string"){var f=c.toLowerCase();_[f]&&(i=f),n&&(_[f]=n,i=f);var p=c.split("-");if(!i&&p.length>1)return m(p[0])}else{var S=c.name;_[S]=c,i=S}return!u&&i&&(j=i),i||!u&&j},D=function(m,c){if(N(m))return m.clone();var n=typeof c=="object"?c:{};return n.date=m,n.args=arguments,new re(n)},y=I;y.l=oe,y.i=N,y.w=function(m,c){return D(m,{locale:c.$L,utc:c.$u,x:c.$x,$offset:c.$offset})};var re=(function(){function m(n){this.$L=oe(n.locale,null,!0),this.parse(n),this.$x=this.$x||n.x||{},this[J]=!0}var c=m.prototype;return c.parse=function(n){this.$d=(function(u){var i=u.date,f=u.utc;if(i===null)return new Date(NaN);if(y.u(i))return new Date;if(i instanceof Date)return new Date(i);if(typeof i=="string"&&!/Z$/i.test(i)){var p=i.match(W);if(p){var S=p[2]-1||0,w=(p[7]||"0").substring(0,3);return f?new Date(Date.UTC(p[1],S,p[3]||1,p[4]||0,p[5]||0,p[6]||0,w)):new Date(p[1],S,p[3]||1,p[4]||0,p[5]||0,p[6]||0,w)}}return new Date(i)})(n),this.init()},c.init=function(){var n=this.$d;this.$y=n.getFullYear(),this.$M=n.getMonth(),this.$D=n.getDate(),this.$W=n.getDay(),this.$H=n.getHours(),this.$m=n.getMinutes(),this.$s=n.getSeconds(),this.$ms=n.getMilliseconds()},c.$utils=function(){return y},c.isValid=function(){return this.$d.toString()!==R},c.isSame=function(n,u){var i=D(n);return this.startOf(u)<=i&&i<=this.endOf(u)},c.isAfter=function(n,u){return D(n)<this.startOf(u)},c.isBefore=function(n,u){return this.endOf(u)<D(n)},c.$g=function(n,u,i){return y.u(n)?this[u]:this.set(i,n)},c.unix=function(){return Math.floor(this.valueOf()/1e3)},c.valueOf=function(){return this.$d.getTime()},c.startOf=function(n,u){var i=this,f=!!y.u(u)||u,p=y.p(n),S=function(U,O){var P=y.w(i.$u?Date.UTC(i.$y,O,U):new Date(i.$y,O,U),i);return f?P:P.endOf($)},w=function(U,O){return y.w(i.toDate()[U].apply(i.toDate("s"),(f?[0,0,0,0]:[23,59,59,999]).slice(O)),i)},L=this.$W,C=this.$M,T=this.$D,G="set"+(this.$u?"UTC":"");switch(p){case b:return f?S(1,0):S(31,11);case l:return f?S(1,C):S(0,C+1);case M:var Y=this.$locale().weekStart||0,X=(L<Y?L+7:L)-Y;return S(f?T-X:T+(6-X),C);case $:case x:return w(G+"Hours",0);case E:return w(G+"Minutes",1);case v:return w(G+"Seconds",2);case d:return w(G+"Milliseconds",3);default:return this.clone()}},c.endOf=function(n){return this.startOf(n,!1)},c.$set=function(n,u){var i,f=y.p(n),p="set"+(this.$u?"UTC":""),S=(i={},i[$]=p+"Date",i[x]=p+"Date",i[l]=p+"Month",i[b]=p+"FullYear",i[E]=p+"Hours",i[v]=p+"Minutes",i[d]=p+"Seconds",i[s]=p+"Milliseconds",i)[f],w=f===$?this.$D+(u-this.$W):u;if(f===l||f===b){var L=this.clone().set(x,1);L.$d[S](w),L.init(),this.$d=L.set(x,Math.min(this.$D,L.daysInMonth())).$d}else S&&this.$d[S](w);return this.init(),this},c.set=function(n,u){return this.clone().$set(n,u)},c.get=function(n){return this[y.p(n)]()},c.add=function(n,u){var i,f=this;n=Number(n);var p=y.p(u),S=function(C){var T=D(f);return y.w(T.date(T.date()+Math.round(C*n)),f)};if(p===l)return this.set(l,this.$M+n);if(p===b)return this.set(b,this.$y+n);if(p===$)return S(1);if(p===M)return S(7);var w=(i={},i[v]=r,i[E]=o,i[d]=a,i)[p]||1,L=this.$d.getTime()+n*w;return y.w(L,this)},c.subtract=function(n,u){return this.add(-1*n,u)},c.format=function(n){var u=this,i=this.$locale();if(!this.isValid())return i.invalidDate||R;var f=n||"YYYY-MM-DDTHH:mm:ssZ",p=y.z(this),S=this.$H,w=this.$m,L=this.$M,C=i.weekdays,T=i.months,G=i.meridiem,Y=function(O,P,K,ne){return O&&(O[P]||O(u,f))||K[P].slice(0,ne)},X=function(O){return y.s(S%12||12,O,"0")},U=G||function(O,P,K){var ne=O<12?"AM":"PM";return K?ne.toLowerCase():ne};return f.replace(F,(function(O,P){return P||(function(K){switch(K){case"YY":return String(u.$y).slice(-2);case"YYYY":return y.s(u.$y,4,"0");case"M":return L+1;case"MM":return y.s(L+1,2,"0");case"MMM":return Y(i.monthsShort,L,T,3);case"MMMM":return Y(T,L);case"D":return u.$D;case"DD":return y.s(u.$D,2,"0");case"d":return String(u.$W);case"dd":return Y(i.weekdaysMin,u.$W,C,2);case"ddd":return Y(i.weekdaysShort,u.$W,C,3);case"dddd":return C[u.$W];case"H":return String(S);case"HH":return y.s(S,2,"0");case"h":return X(1);case"hh":return X(2);case"a":return U(S,w,!0);case"A":return U(S,w,!1);case"m":return String(w);case"mm":return y.s(w,2,"0");case"s":return String(u.$s);case"ss":return y.s(u.$s,2,"0");case"SSS":return y.s(u.$ms,3,"0");case"Z":return p}return null})(O)||p.replace(":","")}))},c.utcOffset=function(){return 15*-Math.round(this.$d.getTimezoneOffset()/15)},c.diff=function(n,u,i){var f,p=this,S=y.p(u),w=D(n),L=(w.utcOffset()-this.utcOffset())*r,C=this-w,T=function(){return y.m(p,w)};switch(S){case b:f=T()/12;break;case l:f=T();break;case g:f=T()/3;break;case M:f=(C-L)/6048e5;break;case $:f=(C-L)/864e5;break;case E:f=C/o;break;case v:f=C/r;break;case d:f=C/a;break;default:f=C}return i?f:y.a(f)},c.daysInMonth=function(){return this.endOf(l).$D},c.$locale=function(){return _[this.$L]},c.locale=function(n,u){if(!n)return this.$L;var i=this.clone(),f=oe(n,u,!0);return f&&(i.$L=f),i},c.clone=function(){return y.w(this.$d,this)},c.toDate=function(){return new Date(this.valueOf())},c.toJSON=function(){return this.isValid()?this.toISOString():null},c.toISOString=function(){return this.$d.toISOString()},c.toString=function(){return this.$d.toUTCString()},m})(),xe=re.prototype;return D.prototype=xe,[["$ms",s],["$s",d],["$m",v],["$H",E],["$W",$],["$M",l],["$y",b],["$D",x]].forEach((function(m){xe[m[1]]=function(c){return this.$g(c,m[0],m[1])}})),D.extend=function(m,c){return m.$i||(m(c,re,D),m.$i=!0),D},D.locale=oe,D.isDayjs=N,D.unix=function(m){return D(1e3*m)},D.en=_[j],D.Ls=_,D.p={},D}))})(ce)),ce.exports}var Jt=ot();const ge=at(Jt);var de={exports:{}},Gt=de.exports,Ne;function Qt(){return Ne||(Ne=1,(function(e,t){(function(a,r){e.exports=r()})(Gt,(function(){return function(a,r,o){a=a||{};var s=r.prototype,d={future:"in %s",past:"%s ago",s:"a few seconds",m:"a minute",mm:"%d minutes",h:"an hour",hh:"%d hours",d:"a day",dd:"%d days",M:"a month",MM:"%d months",y:"a year",yy:"%d years"};function v($,M,l,g){return s.fromToBase($,M,l,g)}o.en.relativeTime=d,s.fromToBase=function($,M,l,g,b){for(var x,R,W,F=l.$locale().relativeTime||d,A=a.thresholds||[{l:"s",r:44,d:"second"},{l:"m",r:89},{l:"mm",r:44,d:"minute"},{l:"h",r:89},{l:"hh",r:21,d:"hour"},{l:"d",r:35},{l:"dd",r:25,d:"day"},{l:"M",r:45},{l:"MM",r:10,d:"month"},{l:"y",r:17},{l:"yy",d:"year"}],Z=A.length,I=0;I<Z;I+=1){var j=A[I];j.d&&(x=g?o($).diff(l,j.d,!0):l.diff($,j.d,!0));var _=(a.rounding||Math.round)(Math.abs(x));if(W=x>0,_<=j.r||!j.r){_<=1&&I>0&&(j=A[I-1]);var J=F[j.l];b&&(_=b(""+_)),R=typeof J=="string"?J.replace("%d",_):J(_,M,j.l,W);break}}if(M)return R;var N=W?F.future:F.past;return typeof N=="function"?N(R):N.replace("%s",R)},s.to=function($,M){return v($,M,this,!0)},s.from=function($,M){return v($,M,this)};var E=function($){return $.$u?o.utc():o()};s.toNow=function($){return this.to(E(this),$)},s.fromNow=function($){return this.from(E(this),$)}}}))})(de)),de.exports}var Zt=Qt();const Xt=at(Zt);var le={exports:{}},Kt=le.exports,Ye;function ea(){return Ye||(Ye=1,(function(e,t){(function(a,r){e.exports=r(ot())})(Kt,(function(a){function r(d){return d&&typeof d=="object"&&"default"in d?d:{default:d}}var o=r(a),s={name:"pt-br",weekdays:"domingo_segunda-feira_terça-feira_quarta-feira_quinta-feira_sexta-feira_sábado".split("_"),weekdaysShort:"dom_seg_ter_qua_qui_sex_sáb".split("_"),weekdaysMin:"Do_2ª_3ª_4ª_5ª_6ª_Sá".split("_"),months:"janeiro_fevereiro_março_abril_maio_junho_julho_agosto_setembro_outubro_novembro_dezembro".split("_"),monthsShort:"jan_fev_mar_abr_mai_jun_jul_ago_set_out_nov_dez".split("_"),ordinal:function(d){return d+"º"},formats:{LT:"HH:mm",LTS:"HH:mm:ss",L:"DD/MM/YYYY",LL:"D [de] MMMM [de] YYYY",LLL:"D [de] MMMM [de] YYYY [às] HH:mm",LLLL:"dddd, D [de] MMMM [de] YYYY [às] HH:mm"},relativeTime:{future:"em %s",past:"há %s",s:"poucos segundos",m:"um minuto",mm:"%d minutos",h:"uma hora",hh:"%d horas",d:"um dia",dd:"%d dias",M:"um mês",MM:"%d meses",y:"um ano",yy:"%d anos"}};return o.default.locale(s,null,!0),s}))})(le)),le.exports}ea();ge.extend(Xt);ge.locale("pt-br");const ta=e=>ge(e).fromNow(),aa=e=>ge(e).format("DD/MM/YYYY [às] HH:mm"),oa={voluntario:["Voluntário","success"],doador:["Doador","info"],ambos:["Voluntário e doador","warning"]},ra=e=>{const[t,a]=oa[e.tipo]??["Não informado",""],r=pe(e.projeto);return h`
    <li class="card registro" data-id="${e.id}">
      <div class="card-header">
        <h3 class="card-title">${e.nome}</h3>
        <span class="badge badge-${a}">${t}</span>
      </div>
      <dl class="details">
        <div><dt>Projeto</dt><dd>${r?r.nome:"Qualquer projeto"}</dd></div>
        <div><dt>Cidade</dt><dd>${e.cidade}/${e.estado}</dd></div>
        <div><dt>Contato</dt><dd>${e.email} · ${e.telefone}</dd></div>
        <div><dt>CPF</dt><dd>***.***.***-${e.cpfFinal}</dd></div>
      </dl>
      <div class="card-footer">
        <time datetime="${e.criadoEm}" title="${aa(e.criadoEm)}">Cadastrado ${ta(e.criadoEm)}</time>
        <button class="btn btn-secondary btn-sm" type="button" data-remover="${e.id}">Remover<span class="sr-only"> ${e.nome}</span></button>
      </div>
    </li>`};function na(){const e=Me(),t=ae().map(pe).filter(Boolean),a=r=>e.filter(o=>o.tipo===r||o.tipo==="ambos").length;return h`
    <ul class="stats">
      <li><strong>${e.length}</strong> cadastro(s) salvos</li>
      <li><strong>${a("voluntario")}</strong> voluntário(s)</li>
      <li><strong>${a("doador")}</strong> doador(es)</li>
      <li><strong>${t.length}</strong> projeto(s) favorito(s)</li>
    </ul>
    ${t.length?h`<p class="muted">Favoritos: ${t.map((r,o)=>h`${o?", ":""}<a href="#/projetos/${r.slug}">${r.nome}</a>`)}</p>`:""}
    <div class="section-heading">
      <h2>Cadastros neste navegador</h2>
      ${e.length?h`<button class="btn btn-secondary btn-sm" type="button" data-limpar>Apagar todos</button>`:""}
    </div>
    ${e.length?h`<ul class="registros grid">${e.map(ra)}</ul>`:h`<div class="empty-state"><p><strong>Nenhum cadastro ainda.</strong></p><p class="muted">Os cadastros feitos aqui ficam salvos só neste navegador.</p><a class="btn btn-primary" href="#/cadastro">Fazer um cadastro</a></div>`}`}const sa=()=>({titulo:"Meu painel",render:()=>h`
    <section class="page-intro" aria-labelledby="titulo-pagina">
      <div class="container">
        <p class="eyebrow">Painel</p>
        <h1 id="titulo-pagina" tabindex="-1">Meu <em>painel.</em></h1>
        <p class="lead">Dados recuperados do localStorage: continuam aqui mesmo depois de fechar o navegador.</p>
      </div>
    </section>
    <section class="section" aria-label="Resumo dos dados salvos"><div class="container stack" data-painel></div></section>`,montar:e=>{const t=e.querySelector("[data-painel]"),a=()=>Ge(t,na()),r=async s=>{const d=s.target.closest("[data-remover]");if(d){const v=d.closest(".registro").querySelector(".card-title").textContent;await Ae({titulo:"Remover cadastro?",mensagem:`O cadastro de ${v} será apagado deste navegador.`,acao:"Remover"})&&(lt(d.dataset.remover),me("Cadastro removido.","info"))}s.target.closest("[data-limpar]")&&await Ae({titulo:"Apagar todos os cadastros?",mensagem:"Esta ação não pode ser desfeita.",acao:"Apagar todos"})&&(ut(),me("Todos os cadastros foram apagados.","info"))};t.addEventListener("click",r);const o=()=>a();return ze(o),a(),()=>{t.removeEventListener("click",r),it(o)}}}),ia=e=>({titulo:"Página não encontrada",render:()=>h`
    <section class="section" aria-labelledby="titulo-pagina">
      <div class="container stack narrow">
        <p class="eyebrow">Erro 404</p>
        <h1 id="titulo-pagina" tabindex="-1">Página não encontrada.</h1>
        <p class="lead">Não existe conteúdo em <code>${e}</code>. Confira o endereço ou volte ao início.</p>
        <div class="button-row"><a class="btn btn-primary" href="#/">Ir para o início</a><a class="btn btn-secondary" href="#/projetos">Ver projetos</a></div>
      </div>
    </section>`});document.documentElement.classList.replace("no-js","js");const $e=document.getElementById("app");let Ue=!0;const ca=nt({saida:$e,rotas:[{padrao:/^\/$/,tela:Ot},{padrao:/^\/projetos$/,tela:Tt},{padrao:/^\/projetos\/(?<slug>[a-z-]+)$/,tela:qt},{padrao:/^\/cadastro$/,tela:Vt},{padrao:/^\/painel$/,tela:sa}],naoEncontrada:ia,aoNavegar:e=>{Mt(e),window.scrollTo({top:0}),Ue||$e.querySelector("h1")?.focus(),Ue=!1}});document.querySelector("[data-pular]").addEventListener("click",e=>{e.preventDefault(),$e.focus()});Et();Dt();ca.iniciar();
