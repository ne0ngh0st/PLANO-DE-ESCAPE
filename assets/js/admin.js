/* Funcionalidades específicas para página admin_excluidos_observacoes.php */

// Função utilitária para fazer requisições POST
async function postForm(url, data) {
    const formData = new URLSearchParams();
    Object.keys(data).forEach(k => formData.append(k, data[k]));
    const res = await fetch(url, { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
        body: formData.toString() 
    });
    return res.json();
}

// Excluir observação
async function excluirObservacao(id) {
    if (!confirm('Tem certeza que deseja excluir esta observação? Respostas também serão removidas se houver.')) return;
    
    const motivo = prompt('Informe o motivo da exclusão (obrigatório):');
    if (motivo === null) return;
    
    const m = (motivo || '').trim();
    if (!m) { 
        alert('Motivo da exclusão é obrigatório.'); 
        return; 
    }
    
    const resp = await postForm('includes/gerenciar_observacoes.php', { 
        action: 'excluir', 
        id: id, 
        motivo_exclusao: m 
    });
    
    if (resp && resp.success) { 
        location.reload(); 
    } else { 
        alert(resp && resp.message ? resp.message : 'Erro ao excluir.'); 
    }
}

// Editar observação
async function editarObservacao(id, atual) {
    const novo = prompt('Editar observação:', atual || '');
    if (novo === null) return;
    
    const texto = (novo || '').trim();
    if (!texto) { 
        alert('Texto obrigatório'); 
        return; 
    }
    
    const resp = await postForm('includes/gerenciar_observacoes.php', { 
        action: 'editar', 
        id: id, 
        observacao: texto 
    });
    
    if (resp && resp.success) { 
        location.reload(); 
    } else { 
        alert(resp && resp.message ? resp.message : 'Erro ao editar.'); 
    }
}

// Responder observação
async function responderObservacao(parentId) {
    const texto = prompt('Digite a resposta:');
    if (texto === null) return;
    
    const t = (texto || '').trim();
    if (!t) { 
        alert('Texto obrigatório'); 
        return; 
    }
    
    const resp = await postForm('includes/gerenciar_observacoes.php', { 
        action: 'responder', 
        parent_id: parentId, 
        observacao: t 
    });
    
    if (resp && resp.success) { 
        location.reload(); 
    } else { 
        alert(resp && resp.message ? resp.message : 'Erro ao responder.'); 
    }
}

// Restaurar cliente
async function restaurarCliente(cnpj) {
    if (!cnpj) { 
        alert('CNPJ inválido'); 
        return; 
    }
    
    if (!confirm('Confirmar restauração do cliente?')) return;
    
    const resp = await postForm('includes/restaurar_cliente.php', { 
        cnpj: cnpj 
    });
    
    if (resp && resp.success) { 
        location.reload(); 
    } else { 
        alert(resp && resp.message ? resp.message : 'Erro ao restaurar.'); 
    }
}

// Restaurar observação
async function restaurarObservacao(observacaoId) {
    if (!observacaoId) { 
        alert('ID inválido'); 
        return; 
    }
    
    if (!confirm('Confirmar restauração desta observação?')) return;
    
    const resp = await postForm('includes/restaurar_observacao.php', { 
        observacao_id: observacaoId 
    });
    
    if (resp && resp.success) { 
        location.reload(); 
    } else { 
        alert(resp && resp.message ? resp.message : 'Erro ao restaurar.'); 
    }
}
