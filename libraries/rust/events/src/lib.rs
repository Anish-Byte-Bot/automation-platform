#[rustfmt::skip]
mod structs;

use async_std::prelude::Stream;
use rpc_support::rpc_error::RpcError;
use rpc_support::{read_request, send_response, send_stream_response, RawRpcClient};
use std::pin::Pin;
use std::sync::atomic::{AtomicU64, Ordering};
use std::sync::Arc;
pub use structs::*;
use thiserror::Error;
use tokio::io::BufReader;
use tokio::net::TcpListener;
use tokio::net::TcpStream;
use tokio::sync::Mutex;
use tracing::info;

#[macro_use]
extern crate async_trait;

pub struct Client {
    id: AtomicU64,
    raw: RawRpcClient,
}

pub struct Server<T>
where
    T: Rpc + Send + Sync,
{
    tcp: Arc<Mutex<TcpListener>>,
    rpc: Arc<Mutex<T>>,
}

impl Client {
    /// # Errors
    /// Will return an error when the TCP connection fails.
    pub async fn new(addr: &str) -> Result<Self, RpcError> {
        let tcp = TcpStream::connect(addr).await?;

        Ok(Client {
            raw: RawRpcClient::new(tcp),
            id: AtomicU64::new(0),
        })
    }
}

// todo this impl should be autogenerated!
#[async_trait]
impl Rpc for Client {
    async fn subscribe(
        &mut self,
        request: SubscribeRequest,
        metadata: Metadata,
    ) -> Result<Pin<Box<dyn Stream<Item = Result<Event, RpcError>> + Unpin + Send>>, RpcError> {
        self.raw
            .send_rpc_stream_request(
                self.id.fetch_add(1, Ordering::AcqRel),
                "subscribe",
                &request,
                &metadata,
            )
            .await
    }

    async fn send_event(&mut self, request: Event, metadata: Metadata) -> Result<(), RpcError> {
        self.raw
            .send_rpc(
                self.id.fetch_add(1, Ordering::AcqRel),
                "send_event",
                &request,
                &metadata,
            )
            .await
    }
}

#[derive(Debug, Error)]
pub enum RunError {
    #[error("{0}")]
    IoError(#[from] tokio::io::Error),
}

#[derive(Debug, Error)]
pub enum ClientError {
    #[error("{0}")]
    IoError(#[from] tokio::io::Error),
    #[error("{0}")]
    JsonError(#[from] serde_json::Error),
    #[error("{0}")]
    RpcError(#[from] RpcError),
}

impl<T> Server<T>
where
    T: Rpc + Send + Sync + 'static,
{
    /// # Errors
    /// Will return an error when establishing the TCP Listener fails
    pub async fn new(addr: &str, rpc: Arc<Mutex<T>>) -> Result<Self, RpcError> {
        let tcp = Arc::new(Mutex::new(TcpListener::bind(addr).await?));
        Ok(Server { tcp, rpc })
    }

    async fn handle_client(socket: TcpStream, rpc: Arc<Mutex<T>>) -> Result<(), ClientError> {
        let mut socket = socket;
        let (read, mut write) = socket.split();
        let mut reader = BufReader::new(read);

        loop {
            let (payload_line, method_name, request_id, metadata) =
                read_request(&mut reader).await?;

            match method_name.as_str() {
                "send_event" => {
                    let result = rpc
                        .lock()
                        .await
                        .send_event(serde_json::from_str(&payload_line)?, metadata)
                        .await;

                    send_response(&mut write, result, request_id, false).await?;
                }
                "subscribe" => {
                    let result = rpc
                        .lock()
                        .await
                        .subscribe(serde_json::from_str(&payload_line)?, metadata)
                        .await;

                    send_stream_response(&mut write, result, request_id).await?;
                }
                // fixme do not panic here!
                _ => panic!("Unknown method name: {}", method_name),
            };
        }
    }

    /// # Errors
    /// Will return an error if the connection fails
    pub async fn run(self) -> Result<(), RunError> {
        loop {
            let (socket, address) = self.tcp.lock().await.accept().await?;
            info!("New client connected: {}", address);

            let rpc = self.rpc.clone();

            tokio::spawn(platform::async_infra::run_with_error_handling(
                Self::handle_client(socket, rpc),
            ));
        }
    }
}

#[cfg(test)]
mod test {}
