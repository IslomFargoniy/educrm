package uz.educrm.app.ui

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.launch
import uz.educrm.app.R

/** One page from the server: rows plus the next page number (null at the end). */
data class PageResult<T>(val rows: List<T>, val nextPage: Int?, val total: Int)

/**
 * A list loaded page by page: the screen shows what is loaded and asks for more near the end,
 * so thousands of rows never load (or render) at once.
 */
class Paged<T>(private val scope: CoroutineScope, private val fetch: suspend (page: Int) -> PageResult<T>) {
    val items = mutableStateListOf<T>()
    var total by mutableStateOf(0)
        private set
    var loading by mutableStateOf(false)
        private set
    var error by mutableStateOf<String?>(null)
        private set
    var started by mutableStateOf(false)
        private set
    private var nextPage: Int? = 1
    private var job: Job? = null

    val hasMore: Boolean get() = nextPage != null

    fun loadMore() {
        val page = nextPage ?: return
        if (loading) return
        loading = true
        job = scope.launch {
            runCatching { fetch(page) }
                .onSuccess {
                    items.addAll(it.rows)
                    nextPage = it.nextPage
                    total = it.total
                    error = null
                }
                .onFailure { error = it.message ?: "error" }
            loading = false
            started = true
        }
    }

    /** Start over (new search, pull to refresh, after an edit). */
    fun reset() {
        job?.cancel()
        loading = false
        items.clear()
        nextPage = 1
        error = null
        started = false
        loadMore()
    }
}

/** A [Paged] list that starts over whenever [key] changes (e.g. the search text). */
@Composable
fun <T> rememberPaged(key: Any?, fetch: suspend (page: Int) -> PageResult<T>): Paged<T> {
    val scope = rememberCoroutineScope()
    val paged = remember(key) { Paged(scope, fetch) }
    LaunchedEffect(paged) { paged.reset() }
    return paged
}

/**
 * Footer of a paged list: loads the next page as soon as it scrolls into view, shows a spinner,
 * or a retry button after an error.
 */
fun <T> LazyListScope.pagingFooter(paged: Paged<T>, emptyText: String = "—") {
    item(key = "paging-footer") {
        when {
            paged.error != null -> Box(Modifier.fillMaxWidth().padding(8.dp), contentAlignment = Alignment.Center) {
                TextButton({ paged.loadMore() }) { Text(stringResource(R.string.retry)) }
            }
            paged.hasMore -> {
                LaunchedEffect(paged.items.size) { paged.loadMore() }
                Box(Modifier.fillMaxWidth().padding(12.dp), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(Modifier.size(24.dp), strokeWidth = 2.dp)
                }
            }
            paged.started && paged.items.isEmpty() -> Text(emptyText, color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.padding(16.dp))
        }
    }
}
