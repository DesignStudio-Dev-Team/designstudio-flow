import{F as e,I as t,J as n,M as r,N as i,O as a,Q as o,St as s,Tt as c,U as ee,_ as l,b as u,ct as d,h as f,j as p,k as m,kt as h,t as g,v as _,x as v,xt as y,y as b}from"./_plugin-vue_export-helper-BTdm89ns.js";import{c as x}from"./main-Cb1aDc6j.js";import{t as S}from"./InlineText-Dx3ndTtF.js";var C=h({default:()=>D});d(),a(),n();var w={key:0,class:`dsf-form-with-content__header`},T=[`contenteditable`],te={key:0,class:`dsf-form-with-content__media-wrap`},ne=[`src`],re=[`src`],ie={key:2,class:`dsf-form-with-content__video-wrap`},ae={class:`dsf-form-with-content__video dsf-form-with-content__video--file`,autoplay:``,muted:``,loop:``},oe=[`src`,`type`],se={key:3,class:`dsf-form-with-content__video-wrap`},ce=[`src`],le={key:1,class:`dsf-form-with-content__video-placeholder`},ue={key:0,class:`dsf-form-with-content__form-placeholder`},de={class:`dsf-form-with-content__form-name`},fe={class:`dsf-form-with-content__code`},pe=[`innerHTML`],me={key:1,class:`dsf-form-with-content__empty`},he=[`innerHTML`],ge={key:1,class:`dsf-form-with-content__empty`},E=`dsf-form-with-content-gravity-overrides`,_e=`<p><b>Your dream backyard starts here!</b></p><p>Fill out the form and we'll be in touch as soon as possible.</p>`,D=g({__name:`FormWithContentPreview`,props:{settings:{type:Object,default:()=>({})},isEditor:{type:Boolean,default:!1},previewMode:{type:String,default:`desktop`}},setup(n){let a=n,d=m(`dsfRenderMode`,`live`),h=o(null),g=o(null),C=o(``),D=null,O=null,k=null,A=!1,j=l(()=>a.settings?.formSide||`right`),M=l(()=>a.settings?.formSource||`dsf`),N=l(()=>M.value!==`embed`),P=l(()=>{let e=x(a.settings||{},a.previewMode,`padding`)??60,t=x(a.settings||{},a.previewMode,`paddingX`)??24;return{backgroundColor:a.settings?.backgroundColor||`#FFFFFF`,padding:`${e}px ${t}px`}}),F=l(()=>{let e=a.settings?.columnRatio||`1-1`,t=`minmax(0, 1fr) minmax(0, 1fr)`;return e===`3-2`?t=j.value===`left`?`minmax(0, 2fr) minmax(0, 3fr)`:`minmax(0, 3fr) minmax(0, 2fr)`:e===`2-3`&&(t=j.value===`left`?`minmax(0, 3fr) minmax(0, 2fr)`:`minmax(0, 2fr) minmax(0, 3fr)`),t}),I=l(()=>({backgroundColor:a.settings?.contentBg||`#FFFFFF`})),ve=l(()=>({color:a.settings?.textColor||`#1F2937`})),ye=l(()=>({backgroundColor:a.settings?.formBg||`#FFFFFF`})),L=l(()=>a.settings?.mediaType===`image`),R=l(()=>L.value&&!!a.settings?.image),z=l(()=>!L.value&&!!a.settings?.videoFile),B=l(()=>!L.value&&!!V.value),be=l(()=>{let e=(a.settings?.videoFile||``).toLowerCase();return e.endsWith(`.webm`)?`video/webm`:e.endsWith(`.ogg`)||e.endsWith(`.ogv`)?`video/ogg`:`video/mp4`}),V=l(()=>{let e=(a.settings?.video||``).trim();if(!e)return``;if(e.includes(`/embed/`)||e.includes(`player.vimeo.com`))return e;let t=e.match(/youtu\.be\/([^?&]+)/);if(t)return`https://www.youtube.com/embed/${t[1]}`;let n=e.match(/[?&]v=([^&]+)/);if(n)return`https://www.youtube.com/embed/${n[1]}`;let r=e.match(/shorts\/([^?&]+)/);if(r)return`https://www.youtube.com/embed/${r[1]}`;let i=e.match(/vimeo\.com\/(\d+)/);return i?`https://player.vimeo.com/video/${i[1]}`:``}),xe=typeof window<`u`&&window.dsfEditorData?.forms||[],H=l(()=>{let e=a.settings?.formId,t=Number.parseInt(e,10);return Number.isFinite(t)&&t>0?String(t):``}),Se=l(()=>(a.settings?.formTitle||``).trim()||(H.value?xe.find(e=>String(e?.id||``)===H.value)?.title||`Form #${H.value}`:`No form selected`)),Ce=l(()=>H.value?`[dsform id='${H.value}']`:`[dsform id='']`),U=l(()=>a.settings?.renderedFormHtml||``),W=l(()=>N.value?``:a.settings?.renderedEmbedHtml||a.settings?.embedCode||``),G=l(()=>N.value||!Array.isArray(a.settings?.renderedEmbedScripts)?[]:a.settings.renderedEmbedScripts),K=l(()=>G.value.length?JSON.stringify(G.value.map(e=>e?.code||``)):``);function q(){a.isEditor||d===`snapshot`||A||!U.value&&!W.value||!h.value||(we(),J(),typeof window?.dsfInitForms==`function`&&window.dsfInitForms(h.value),Ee(),J(),De())}function we(){if(typeof document>`u`)return;let e=document.getElementById(E);e&&e.remove();let t=document.createElement(`style`);t.id=E,t.textContent=`
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper *,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper.gravity-theme,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper.gravity-theme * {
  font-family: var(--dsf-theme-body-font, inherit) !important;
  line-height: 1.65 !important;
  box-sizing: border-box !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper p,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper label,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper legend,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gform-field-label,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gfield_label,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gfield_description,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gchoice,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gchoice label,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gfield_checkbox label,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gfield_radio label,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_container input,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_container textarea,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_container select,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gform_button,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gform_next_button,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gform_previous_button,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gf_progressbar_title {
  font-family: var(--dsf-theme-body-font, inherit) !important;
  font-size: var(--dsf-theme-text-base, 16px) !important;
  line-height: 1.65 !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_body,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_fields,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gfield,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .ginput_container {
  min-width: 0 !important;
  max-width: 100% !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gfield {
  margin: 0 !important;
  padding: 0 !important;
  text-indent: 0 !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gfield_checkbox,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gfield_radio {
  display: grid !important;
  gap: .4rem !important;
  margin: .35rem 0 0 !important;
  padding: 0 !important;
  list-style: none !important;
}

/* Field-level labels are always bold (every field type). Individual checkbox/radio
   choice labels are intentionally left at normal weight. */
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gfield_label,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper legend.gfield_label,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gform-field-label:not(.gform-field-label--type-inline) {
  font-weight: 700 !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gchoice label,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gfield_checkbox label,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gfield_radio label,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gform-field-label--type-inline {
  font-weight: 400 !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] legend,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper legend.gfield_label,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper.gravity-theme legend.gfield_label {
  margin-bottom: 0 !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_ajax_spinner,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform-loader,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] [id^="gform_ajax_spinner_"] {
  width: 16px !important;
  height: 16px !important;
  max-width: 16px !important;
  max-height: 16px !important;
  min-width: 16px !important;
  min-height: 16px !important;
  margin-left: .5rem !important;
  border-width: 2px !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gchoice {
  display: flex !important;
  align-items: center !important;
  gap: .5rem !important;
  line-height: 1.3 !important;
  margin-bottom: 0 !important;
  margin-left: 0 !important;
  padding: 0 !important;
  text-indent: 0 !important;
  list-style: none !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper input[type="checkbox"],
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper input[type="radio"],
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gchoice > input[type="checkbox"],
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gchoice > input[type="radio"] {
  position: static !important;
  display: inline-block !important;
  width: 20px !important;
  height: 20px !important;
  min-width: 20px !important;
  min-height: 20px !important;
  flex: 0 0 20px !important;
  margin: 0 !important;
  padding: 0 !important;
  transform: none !important;
  opacity: 1 !important;
  appearance: auto !important;
  -webkit-appearance: auto !important;
  accent-color: #aaa !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gchoice > label {
  margin: 0 !important;
  display: inline-flex !important;
  align-items: center !important;
  min-width: 0 !important;
  padding: 0 !important;
  text-indent: 0 !important;
  line-height: 1.3 !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]):not([type="image"]),
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper select,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper textarea {
  box-sizing: border-box !important;
  max-width: 100% !important;
  min-height: 42px !important;
  padding: .65rem .8rem !important;
  border: 1px solid #cbd5e1 !important;
  border-radius: 6px !important;
  background: #fff !important;
  color: inherit !important;
  box-shadow: none !important;
  text-indent: 0 !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper textarea {
  min-height: 110px !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gform_button,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gform_next_button,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .gform_previous_button {
  margin: .75rem .5rem 0 0 !important;
  padding: .75rem 1.2rem !important;
  border: 0 !important;
  border-radius: 6px !important;
  cursor: pointer !important;
  text-indent: 0 !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .akismet-fields-container {
  display: none !important;
  visibility: hidden !important;
  height: 0 !important;
  overflow: hidden !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gf_progressbar_title {
  display: flex !important;
  align-items: baseline !important;
  gap: .25rem !important;
  margin: 0 0 .75rem !important;
  padding: 0 !important;
  color: var(--dsf-gray-600, #4B5563) !important;
  font-size: .75rem !important;
  font-weight: 600 !important;
  line-height: 1.25 !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .dsf-gform-required-legend--inline {
  position: static !important;
  margin: 0 0 0 auto !important;
  padding: 0 !important;
  max-width: 48% !important;
  flex: 0 1 auto !important;
  color: var(--dsf-gray-600, #4B5563) !important;
  font-size: .625rem !important;
  line-height: 1.25 !important;
  text-align: right !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex {
  display: grid !important;
  grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
  column-gap: 16px !important;
  row-gap: .75rem !important;
  width: 100% !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex > span,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex > div:not(.gf_clear),
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex .gform-grid-col {
  display: block !important;
  width: 100% !important;
  max-width: 100% !important;
  min-width: 0 !important;
  margin-left: 0 !important;
  margin-right: 0 !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex .name_first,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex .address_city,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex .address_zip {
  grid-column: 1 / span 1 !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex .name_last,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex .address_state {
  grid-column: 2 / span 1 !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex .ginput_full,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex .address_line_1,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex .address_line_2,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex .address_country {
  grid-column: 1 / -1 !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex input,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex select,
body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex textarea {
  width: 100% !important;
  max-width: 100% !important;
}

body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex .gf_clear {
  display: none !important;
}

@media (max-width: 700px) {
  body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex {
    grid-template-columns: 1fr !important;
  }

  body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex > span,
  body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex > div:not(.gf_clear),
  body.dsf-theme-form-repair-active [data-dsf-form-with-content-form][data-dsf-form-with-content-form][data-dsf-form-with-content-form] .gform_wrapper .ginput_complex .gform-grid-col {
    grid-column: 1 / -1 !important;
  }
}
`,document.head.appendChild(t)}function J(){let e=h.value;e&&(e.querySelectorAll(`.akismet-fields-container`).forEach(e=>{e.setAttribute(`hidden`,``),e.setAttribute(`aria-hidden`,`true`)}),e.querySelectorAll(`.gform_wrapper legend, .gform_wrapper legend.gfield_label, .gform_wrapper.gravity-theme legend.gfield_label`).forEach(e=>{e.style.setProperty(`margin-bottom`,`0`,`important`),e.style.setProperty(`margin-block-end`,`0`,`important`)}),e.querySelectorAll(`.gform_wrapper`).forEach(e=>{let t=e.querySelector(`.gform_heading .gform_required_legend`),n=e.querySelector(`.gf_progressbar_title`);!t||!n||n.contains(t)||(t.classList.add(`dsf-gform-required-legend--inline`),n.appendChild(t))}))}function Te(e){if(typeof document>`u`||!e||A)return;let t=new DOMParser().parseFromString(e,`text/html`),n=Array.from(t.querySelectorAll(`script`));if(!n.length)return;let r=JSON.stringify(n.map(e=>e.textContent||``));C.value!==r&&(C.value=r,Y(n.map(e=>({code:e.textContent||``}))))}function Ee(){if(N.value){if(!U.value)return;Te(U.value)}else{if(!G.value.length||!K.value||C.value===K.value)return;C.value=K.value,Y(G.value)}}function Y(e,t=0){if(!A){if(e.some(e=>/\bgform\b|gravity_form|gform_wrapper/.test(e?.code||``))&&typeof window<`u`&&!window.gform&&t<80){k=window.setTimeout(()=>{k=null,Y(e,t+1)},50);return}e.forEach(e=>{let t=(e?.code||``).trim();if(!t)return;let n=document.createElement(`script`);n.type=`text/javascript`,n.text=t,document.body.appendChild(n),n.remove()}),X()}}function X(){let e=h.value;if(!e||typeof window>`u`)return;let t=e.querySelectorAll(`.gform_wrapper`);t.length&&t.forEach(e=>{let t=(e.id||``).match(/gform_wrapper_(\d+)/),n=t?Number.parseInt(t[1],10):0;if(!n)return;let r=e.querySelector(`form`),i=Number.parseInt(r?.querySelector(`input[name^='gform_source_page_number_']`)?.value,10)||1;if(window.jQuery)try{window.jQuery(document).trigger(`gform_post_render`,[n,i])}catch{}if(window.gform&&typeof window.gform.doAction==`function`)try{window.gform.doAction(`gform_post_render`,n,i)}catch{}})}function De(){a.isEditor||D||typeof window>`u`||(D=(e,t)=>{J(),Z(t)},O=e=>{J(),Z(e?.detail?.formId)},window.jQuery&&window.jQuery(document).on(`gform_page_loaded`,D),document.addEventListener(`gform/ajax/post_page_change`,O))}function Z(e){let t=h.value;if(!t||typeof window>`u`)return;let n=Number.parseInt(e,10);if(Number.isFinite(n)&&n>0&&!t.querySelector(`#gform_${n}`))return;let r=t.getBoundingClientRect().top+window.pageYOffset-24;window.scrollTo({top:Math.max(r,0),behavior:`smooth`})}function Q(){if(!g.value)return;let e=a.settings?.content||_e;g.value.innerHTML!==e&&(g.value.innerHTML=e)}function $(){if(!a.isEditor||!g.value)return;let e=g.value.innerHTML;e!==a.settings?.content&&(a.settings.content=e)}function Oe(e){if(!a.isEditor)return;e.preventDefault();let t=e.clipboardData?.getData(`text/plain`)||``,n=window.getSelection();n?.rangeCount&&(n.deleteFromDocument(),n.getRangeAt(0).insertNode(document.createTextNode(t)),n.collapseToEnd(),$())}return i(()=>{Q(),p(q)}),e(()=>p(q)),ee(()=>a.settings?.content,()=>{typeof document<`u`&&document.activeElement===g.value||Q()}),r(()=>{A=!0,k!==null&&(clearTimeout(k),k=null),typeof window<`u`&&window.jQuery&&D&&window.jQuery(document).off(`gform_page_loaded`,D),typeof document<`u`&&O&&document.removeEventListener(`gform/ajax/post_page_change`,O)}),(e,r)=>(t(),v(`div`,{class:`dsf-block-preview dsf-form-with-content`,style:s(P.value)},[n.isEditor||n.settings.sectionTitle||n.settings.showDivider?(t(),v(`div`,w,[n.isEditor||n.settings.sectionTitle?(t(),b(S,{key:0,tagName:`h2`,class:`dsf-form-with-content__section-title`,style:s({color:n.settings.titleColor||`#1F2937`}),modelValue:n.settings.sectionTitle,"onUpdate:modelValue":r[0]||=e=>n.settings.sectionTitle=e,"is-editor":n.isEditor,placeholder:`Enter Section Title`},null,8,[`style`,`modelValue`,`is-editor`])):u(``,!0),n.settings.showDivider?(t(),v(`hr`,{key:1,class:`dsf-form-with-content__divider`,style:s({borderColor:n.settings.dividerColor||`#E5E7EB`})},null,4)):u(``,!0)])):u(``,!0),_(`div`,{class:y([`dsf-form-with-content__grid`,j.value===`left`?`dsf-form-with-content__grid--form-left`:`dsf-form-with-content__grid--form-right`]),style:s({"--grid-cols":F.value})},[_(`div`,{class:`dsf-form-with-content__col dsf-form-with-content__col--content`,style:s(I.value)},[_(`div`,{ref_key:`contentEditor`,ref:g,class:y([`dsf-form-with-content__content`,{"dsf-form-with-content__content--editable":n.isEditor}]),style:s(ve.value),contenteditable:n.isEditor,spellcheck:`true`,onBlur:$,onPaste:Oe},null,46,T),R.value||z.value||B.value?(t(),v(`div`,te,[n.settings.logo?(t(),v(`img`,{key:0,src:n.settings.logo,class:y([`dsf-form-with-content__logo`,{"dsf-form-with-content__logo--padded":n.settings.logoPadding}]),alt:`Logo`},null,10,ne)):u(``,!0),R.value?(t(),v(`img`,{key:1,src:n.settings.image,class:`dsf-form-with-content__image`,alt:``},null,8,re)):z.value?(t(),v(`div`,ie,[_(`video`,ae,[_(`source`,{src:n.settings.videoFile,type:be.value},null,8,oe)])])):B.value?(t(),v(`div`,se,[_(`iframe`,{src:V.value,class:`dsf-form-with-content__video`,frameborder:`0`,allow:`autoplay; fullscreen; picture-in-picture`,allowfullscreen:``},null,8,ce)])):u(``,!0)])):n.settings.video&&n.isEditor&&!L.value.value?(t(),v(`div`,le,[_(`span`,null,`Video: `+c(n.settings.video),1)])):u(``,!0)],4),_(`div`,{class:`dsf-form-with-content__col dsf-form-with-content__col--form`,style:s(ye.value)},[N.value?(t(),v(f,{key:0},[n.isEditor?(t(),v(`div`,ue,[r[1]||=_(`div`,{class:`dsf-form-with-content__badge`},` DesignStudio Flow Form `,-1),_(`div`,de,c(Se.value),1),r[2]||=_(`p`,{class:`dsf-form-with-content__hint`},` The live form will render here on the frontend. `,-1),_(`code`,fe,c(Ce.value),1)])):(t(),v(`div`,{key:1,ref_key:`frontendRoot`,ref:h,class:`dsf-form-with-content__form-frontend`,"data-dsf-form-with-content-form":``},[U.value?(t(),v(`div`,{key:0,innerHTML:U.value},null,8,pe)):(t(),v(`div`,me,c(H.value?`Form preview is loading.`:`Select a form in the block settings.`),1))],512))],64)):(t(),v(`div`,{key:1,ref_key:`frontendRoot`,ref:h,class:`dsf-form-with-content__form-frontend`,"data-dsf-form-with-content-form":``},[W.value?(t(),v(`div`,{key:0,innerHTML:W.value},null,8,he)):(t(),v(`div`,ge,` Add your content in the block settings. `))],512))],4)],6)],4))}},[[`__scopeId`,`data-v-1fb5d5e5`]]);export{C as n,D as t};