// Relatório de Clientes Inativos - JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar funcionalidades
    initializeFilters();
    initializeTableSorting();
    initializeExportFunctions();
});

// Inicializar filtros
function initializeFilters() {
    const form = document.querySelector('.filters-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Adicionar loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Carregando...';
                submitBtn.disabled = true;
            }
        });
    }
}

// Inicializar ordenação de tabelas
function initializeTableSorting() {
    const tables = document.querySelectorAll('.stats-table, .clients-table');
    
    tables.forEach(table => {
        const headers = table.querySelectorAll('th');
        headers.forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => sortTable(table, index));
        });
    });
}

// Função de ordenação
function sortTable(table, columnIndex) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    const isNumeric = columnIndex === 1 || columnIndex === 2 || columnIndex === 4 || columnIndex === 5; // Colunas numéricas
    
    rows.sort((a, b) => {
        const aText = a.cells[columnIndex].textContent.trim();
        const bText = b.cells[columnIndex].textContent.trim();
        
        if (isNumeric) {
            const aNum = parseFloat(aText.replace(/[^\d.-]/g, ''));
            const bNum = parseFloat(bText.replace(/[^\d.-]/g, ''));
            return aNum - bNum;
        } else {
            return aText.localeCompare(bText, 'pt-BR');
        }
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// Inicializar funções de exportação
function initializeExportFunctions() {
    // Adicionar botões de exportação se não existirem
    const exportSection = document.querySelector('.export-section');
    if (!exportSection) {
        createExportSection();
    }
}

// Criar seção de exportação
function createExportSection() {
    const container = document.querySelector('.container');
    const exportSection = document.createElement('div');
    exportSection.className = 'export-section';
    exportSection.innerHTML = `
        <h3><i class="fas fa-download"></i> Exportar Dados</h3>
        <div class="export-buttons">
            <button onclick="exportarExcel()" class="btn btn-primary">
                <i class="fas fa-file-excel"></i> Exportar Excel
            </button>
            <button onclick="exportarCSV()" class="btn btn-secondary">
                <i class="fas fa-file-csv"></i> Exportar CSV
            </button>
            <button onclick="imprimirRelatorio()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    `;
    
    // Inserir antes da primeira seção
    const firstSection = container.querySelector('.filters-section');
    if (firstSection) {
        container.insertBefore(exportSection, firstSection);
    } else {
        container.appendChild(exportSection);
    }
}

// Exportar para Excel
function exportarExcel() {
    showLoading('Preparando arquivo Excel...');
    
    // Coletar parâmetros atuais
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('export', 'excel');
    
    // Redirecionar para exportação
    window.location.href = window.location.pathname + '?' + urlParams.toString();
    
    hideLoading();
}

// Exportar para CSV
function exportarCSV() {
    showLoading('Preparando arquivo CSV...');
    
    // Coletar parâmetros atuais
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('export', 'csv');
    
    // Redirecionar para exportação
    window.location.href = window.location.pathname + '?' + urlParams.toString();
    
    hideLoading();
}

// Imprimir relatório
function imprimirRelatorio() {
    window.print();
}

// Coletar dados das tabelas
function collectTableData() {
    const data = {
        resumo: getSummaryData(),
        estatisticas: getStatsData(),
        clientes: getClientsData()
    };
    
    return data;
}

// Obter dados do resumo
function getSummaryData() {
    const cards = document.querySelectorAll('.summary-card');
    return {
        totalClientes: cards[0]?.querySelector('h3')?.textContent || '0',
        mediaDias: cards[1]?.querySelector('h3')?.textContent || '0',
        totalFaturado: cards[2]?.querySelector('h3')?.textContent || 'R$ 0,00'
    };
}

// Obter dados das estatísticas
function getStatsData() {
    const table = document.querySelector('.stats-table tbody');
    if (!table) return [];
    
    const rows = table.querySelectorAll('tr');
    return Array.from(rows).map(row => {
        const cells = row.querySelectorAll('td');
        return {
            vendedor: cells[0]?.textContent?.trim() || '',
            clientesInativos: cells[1]?.textContent?.trim() || '0',
            mediaDias: cells[2]?.textContent?.trim() || '0',
            totalFaturado: cells[3]?.textContent?.trim() || 'R$ 0,00'
        };
    });
}

// Obter dados dos clientes
function getClientsData() {
    const table = document.querySelector('.clients-table tbody');
    if (!table) return [];
    
    const rows = table.querySelectorAll('tr');
    return Array.from(rows).map(row => {
        const cells = row.querySelectorAll('td');
        return {
            cnpj: cells[0]?.textContent?.trim() || '',
            razaoSocial: cells[1]?.textContent?.trim() || '',
            vendedor: cells[2]?.textContent?.trim() || '',
            ultimaCompra: cells[3]?.textContent?.trim() || '',
            diasSemCompra: cells[4]?.textContent?.trim() || '0',
            valorUltimaCompra: cells[5]?.textContent?.trim() || 'R$ 0,00',
            estado: cells[6]?.textContent?.trim() || '',
            contato: cells[7]?.textContent?.trim() || ''
        };
    });
}

// Criar workbook Excel
function createExcelWorkbook(data) {
    // Esta é uma implementação simplificada
    // Em um ambiente real, você usaria uma biblioteca como SheetJS
    return {
        sheets: [
            {
                name: 'Resumo',
                data: [
                    ['Total de Clientes Inativos', data.resumo.totalClientes],
                    ['Média de Dias sem Compra', data.resumo.mediaDias],
                    ['Total Faturado', data.resumo.totalFaturado]
                ]
            },
            {
                name: 'Estatísticas por Vendedor',
                data: [
                    ['Vendedor', 'Clientes Inativos', 'Média Dias', 'Total Faturado'],
                    ...data.estatisticas.map(stat => [
                        stat.vendedor,
                        stat.clientesInativos,
                        stat.mediaDias,
                        stat.totalFaturado
                    ])
                ]
            },
            {
                name: 'Clientes Inativos',
                data: [
                    ['CNPJ', 'Razão Social', 'Vendedor', 'Última Compra', 'Dias sem Compra', 'Valor Última Compra', 'Estado', 'Contato'],
                    ...data.clientes.map(cliente => [
                        cliente.cnpj,
                        cliente.razaoSocial,
                        cliente.vendedor,
                        cliente.ultimaCompra,
                        cliente.diasSemCompra,
                        cliente.valorUltimaCompra,
                        cliente.estado,
                        cliente.contato
                    ])
                ]
            }
        ]
    };
}

// Converter para CSV
function convertToCSV(data) {
    let csv = '';
    
    // Resumo
    csv += 'RESUMO\n';
    csv += 'Total de Clientes Inativos,' + data.resumo.totalClientes + '\n';
    csv += 'Média de Dias sem Compra,' + data.resumo.mediaDias + '\n';
    csv += 'Total Faturado,' + data.resumo.totalFaturado + '\n\n';
    
    // Estatísticas
    csv += 'ESTATÍSTICAS POR VENDEDOR\n';
    csv += 'Vendedor,Clientes Inativos,Média Dias,Total Faturado\n';
    data.estatisticas.forEach(stat => {
        csv += `"${stat.vendedor}","${stat.clientesInativos}","${stat.mediaDias}","${stat.totalFaturado}"\n`;
    });
    
    csv += '\n';
    
    // Clientes
    csv += 'CLIENTES INATIVOS\n';
    csv += 'CNPJ,Razão Social,Vendedor,Última Compra,Dias sem Compra,Valor Última Compra,Estado,Contato\n';
    data.clientes.forEach(cliente => {
        csv += `"${cliente.cnpj}","${cliente.razaoSocial}","${cliente.vendedor}","${cliente.ultimaCompra}","${cliente.diasSemCompra}","${cliente.valorUltimaCompra}","${cliente.estado}","${cliente.contato}"\n`;
    });
    
    return csv;
}

// Download Excel
function downloadExcel(workbook, filename) {
    // Implementação simplificada - em produção use SheetJS
    const csv = convertToCSV(workbook);
    downloadFile(csv, filename.replace('.xlsx', '.csv'), 'text/csv');
}

// Download CSV
function downloadCSV(content, filename) {
    downloadFile(content, filename, 'text/csv');
}

// Função genérica de download
function downloadFile(content, filename, mimeType) {
    const blob = new Blob([content], { type: mimeType });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
}

// Mostrar loading
function showLoading(message = 'Carregando...') {
    const loading = document.createElement('div');
    loading.id = 'loading-overlay';
    loading.innerHTML = `
        <div style="
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            color: white;
            font-size: 18px;
        ">
            <div style="
                background: white;
                color: #333;
                padding: 30px;
                border-radius: 10px;
                text-align: center;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            ">
                <i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 15px; display: block;"></i>
                ${message}
            </div>
        </div>
    `;
    document.body.appendChild(loading);
}

// Esconder loading
function hideLoading() {
    const loading = document.getElementById('loading-overlay');
    if (loading) {
        loading.remove();
    }
}

// Função para formatar números
function formatNumber(num) {
    return new Intl.NumberFormat('pt-BR').format(num);
}

// Função para formatar moeda
function formatCurrency(num) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(num);
}

// Adicionar tooltips
function addTooltips() {
    const badges = document.querySelectorAll('.dias-badge');
    badges.forEach(badge => {
        const dias = parseInt(badge.textContent);
        let tooltip = '';
        
        if (dias > 365) {
            tooltip = 'Cliente muito inativo - mais de 1 ano sem compra';
        } else if (dias > 180) {
            tooltip = 'Cliente inativo - mais de 6 meses sem compra';
        } else {
            tooltip = 'Cliente com pouca atividade recente';
        }
        
        badge.title = tooltip;
    });
}

// Inicializar tooltips quando a página carregar
document.addEventListener('DOMContentLoaded', addTooltips);
