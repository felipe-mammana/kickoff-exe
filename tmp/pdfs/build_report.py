from pathlib import Path
import re
from xml.sax.saxutils import escape
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, PageBreak, Table, TableStyle, Image, KeepTogether
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
import pypdfium2 as pdfium

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'output/pdf/relatorio-cofre-sessoes.pdf'
OUT.parent.mkdir(parents=True, exist_ok=True)
pdfmetrics.registerFont(TTFont('Segoe', 'C:/Windows/Fonts/segoeui.ttf'))
pdfmetrics.registerFont(TTFont('SegoeBold', 'C:/Windows/Fonts/segoeuib.ttf'))
pdfmetrics.registerFontFamily('Segoe',normal='Segoe',bold='SegoeBold',italic='Segoe',boldItalic='SegoeBold')
blue = colors.HexColor('#0052A1')
ink = colors.HexColor('#222D38')
muted = colors.HexColor('#536373')
styles = {
 'body': ParagraphStyle('body', fontName='Segoe', fontSize=10, leading=15, textColor=ink, spaceAfter=9),
 'h1': ParagraphStyle('h1', fontName='SegoeBold', fontSize=23, leading=29, textColor=blue, spaceAfter=19, keepWithNext=True),
 'h2': ParagraphStyle('h2', fontName='SegoeBold', fontSize=13, leading=18, textColor=blue, spaceBefore=13, spaceAfter=10, keepWithNext=True),
 'cell': ParagraphStyle('cell',fontName='Segoe',fontSize=8.5,leading=12,textColor=ink),
 'th': ParagraphStyle('th',fontName='SegoeBold',fontSize=9,leading=12,textColor=colors.white),
 'cover': ParagraphStyle('cover', fontName='SegoeBold',fontSize=38,leading=44,textColor=ink,spaceAfter=24),
 'sub': ParagraphStyle('sub', fontName='Segoe',fontSize=16,leading=24,textColor=muted,spaceAfter=20),
}
def fmt(s):
    accents = {'Relatorio':'Relatório','relatorio':'relatório','evolucao':'evolução','sessoes':'sessões','Sessoes':'Sessões','seguranca':'segurança','Seguranca':'Segurança','nao':'não','Nao':'Não','producao':'produção','validacao':'validação','Validacao':'Validação','operacao':'operação','Operacao':'Operação','recuperacao':'recuperação','Recuperacao':'Recuperação','substituicao':'substituição','atualizacoes':'atualizações','usuarios':'usuários','usuario':'usuário','Usuario':'Usuário','criptografados':'criptografados','confirmacao':'confirmação','autenticacao':'autenticação','permissoes':'permissões','Permissoes':'Permissões','medio':'médio','Medio':'Médio','diagnostico':'diagnóstico','codigo':'código','Codigo':'Código','codigos':'códigos','implementacao':'implementação','observacoes':'observações','titulo':'título','Titulo':'Título','basico':'básico','periodicos':'periódicos','periodico':'periódico','conclusao':'conclusão','Conclusao':'Conclusão','homologacao':'homologação','avaliacao':'avaliação','rotacao':'rotação','gestao':'gestão','Gestao':'Gestão','evidencias':'evidências','Evidencias':'Evidências','limitacoes':'limitações','sessao':'sessão','Sessao':'Sessão','ultima':'última','Ultima':'Última','ultima':'última','tecnicos':'técnicos','tecnica':'técnica','acoes':'ações','acao':'ação','retencao':'retenção','requisicao':'requisição','requisicoes':'requisições','identificavel':'identificável','atribuicao':'atribuição','aprovacao':'aprovação','criterios':'critérios','Criterios':'Critérios','criterio':'critério','Criterio':'Critério','criterios':'critérios','custodia':'custódia','Saida':'Saída','minimos':'mínimos','Portao':'Portão','indices':'índices','memoria':'memória','latencia':'latência','Latencia':'Latência','maxima':'máxima','maximo':'máximo','minima':'mínima','metricas':'métricas','propria':'própria','dependencias':'dependências','obrigatorio':'obrigatório','exclusao':'exclusão','extracao':'extração','comparacao':'comparação','paralizacao':'paralisação','geracao':'geração','instituicao':'instituição','confianca':'confiança','informacoes':'informações','chave':'chave','necessario':'necessário','necessarios':'necessários','propostas':'propostas','periodo':'período','geografica':'geográfica','sincrona':'síncrona','coordenacao':'coordenação','integracoes':'integrações','reconciliacao':'reconciliação','apos':'após','possivel':'possível','criticos':'críticos','criticas':'críticas','critico':'crítico','funcao':'função','descricao':'descrição','descricoes':'descrições','revisao':'revisão','Revisao':'Revisão','revelacao':'revelação','protecao':'proteção','Protecao':'Proteção','automatica':'automática','cenario':'cenário','mantem':'mantém','ate':'até'}
    s = re.sub(r'\b\w+\b', lambda m: accents.get(m.group(),m.group()), s)
    s = re.sub(r'\[([^\]]+)\]\(([^)]+)\)', r'\1', s)
    return escape(s).replace('`','')
def p(s,style='body'): return Paragraph(fmt(s), styles[style])
logo=ROOT/'public/assets/brand/exe-logo-email.png'
def draw(c,doc):
    w,h=doc.pagesize
    c.setFillColor(blue); c.rect(0,h-7,w,7,fill=1,stroke=0)
    c.setStrokeColor(colors.HexColor('#DCE3EA')); c.line(42,40,w-42,40)
    c.setFont('Segoe',8); c.setFillColor(muted)
    c.drawString(42,26,'EXE  /  COFRE E SESSÕES  /  USO INTERNO')
    c.drawRightString(w-42,26,f'{doc.page:02d}')
    if doc.page>1:
        c.setFont('Segoe',8); c.drawString(42,h-30,'RELATÓRIO DE SEGURANÇA E EVOLUÇÃO')
        c.drawRightString(w-42,h-30,'14 SET 2026')

story=[]
if logo.exists():
    im=Image(str(logo)); im.drawHeight=im.imageHeight*150/im.imageWidth; im.drawWidth=150; im.hAlign='LEFT'; story.append(im)
story += [Spacer(1,65),p('SEGURANÇA • ESCALABILIDADE • CONTINUIDADE','h2'),p('Cofre de senhas\ne sessões'.replace('\n',' / '),'cover'),p('Diagnóstico atual e plano de evolução para uma substituição controlada.','sub'),Spacer(1,20)]
box=Table([[p('PARECER EXECUTIVO','h2')],[p('Candidato à homologação controlada. A substituição integral depende de validação independente, infraestrutura, recuperação e equivalência com o cofre atual.')]],colWidths=[487])
box.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,-1),colors.HexColor('#EDF5FA')),('LEFTPADDING',(0,0),(-1,-1),18),('RIGHTPADDING',(0,0),(-1,-1),18),('BOTTOMPADDING',(0,-1),(-1,-1),16)]))
story += [box,Spacer(1,38),p('14 de setembro de 2026','h2'),p('Escopo: cofre de senhas e sessões.\nBase: evidências locais de implementação e testes. Não constitui certificação ou novo pentest.'),PageBreak()]
source=(ROOT/'docs/relatorio-cofre-sessoes.md').read_text(encoding='utf-8')
lines=source[source.index('## 1.') :].splitlines(); i=0; buf=[]
def flush():
    if buf: story.append(p(' '.join(buf)));buf.clear()
while i<len(lines):
    s=lines[i].strip()
    if s.startswith('# '): i+=1; continue
    if s.startswith('## '):
        flush()
        if s.startswith('## 1.') or s.startswith('## 2.') or s.startswith('## 3.') or s.startswith('## 4.') or s.startswith('## 5.') or s.startswith('## 6.') or s.startswith('## 7.') or s.startswith('## 8.') or s.startswith('## 9.'):
            if not isinstance(story[-1],PageBreak):story.append(PageBreak())
        story.append(p(s[3:],'h1')); i+=1;continue
    if s.startswith('### '): flush();story.append(p(s[4:],'h2'));i+=1;continue
    if s.startswith('|'):
        flush();rows=[]
        while i<len(lines) and lines[i].strip().startswith('|'):
            cells=[x.strip() for x in lines[i].strip().strip('|').split('|')]
            if not all(re.fullmatch(r'[-: ]+',x) for x in cells):rows.append(cells)
            i+=1
        widths=[85,210,216] if len(rows[0])==3 else [151,360]
        table=Table([[p(c,'th' if j==0 else 'cell') for c in row] for j,row in enumerate(rows)],colWidths=widths,repeatRows=1,hAlign='LEFT')
        table.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),blue),('ROWBACKGROUNDS',(0,1),(-1,-1),[colors.HexColor('#F0F5F8'),colors.white]),('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),9),('RIGHTPADDING',(0,0),(-1,-1),9),('TOPPADDING',(0,0),(-1,-1),10),('BOTTOMPADDING',(0,0),(-1,-1),10),('LINEBELOW',(0,1),(-1,-1),.3,colors.HexColor('#DCE3EA'))]))
        if len(rows[0]) == 3:
            table.setStyle(TableStyle([('TOPPADDING',(0,0),(-1,-1),5),('BOTTOMPADDING',(0,0),(-1,-1),5)]))
        story += [table,Spacer(1,14)];continue
    if not s: flush()
    elif s.startswith('- ') or re.match(r'^\d+\. ',s): flush();buf.append(s)
    else: buf.append(s)
    i+=1
flush()
doc=SimpleDocTemplate(str(OUT),pagesize=(595.28,841.89),rightMargin=42,leftMargin=42,topMargin=57,bottomMargin=56,title='Cofre e sessões | Relatório de segurança',author='EXE')
doc.build(story,onFirstPage=draw,onLaterPages=draw)
pdf=pdfium.PdfDocument(str(OUT))
from PIL import Image as PILImage, ImageOps, ImageDraw
thumbs=[]
for n in range(len(pdf)):
    im=pdf[n].render(scale=1).to_pil().convert('RGB')
    im.save(ROOT/f'tmp/pdfs/page-{n+1:02d}.png')
    im.thumbnail((238,337)); thumbs.append(im)
sheet=PILImage.new('RGB',(238*4,365*((len(thumbs)+3)//4)),'#dddddd')
for n,im in enumerate(thumbs): sheet.paste(im,((n%4)*238,(n//4)*365))
sheet.save(ROOT/'tmp/pdfs/contact-sheet.png')
print(f'{len(pdf)} pages: {OUT}')
