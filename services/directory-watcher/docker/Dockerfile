FROM rust:1.64.0-slim-bullseye AS planner

RUN cargo install cargo-chef

COPY . /opt/directory-watcher/
WORKDIR /opt/directory-watcher/services/directory-watcher/

RUN cargo chef prepare --recipe-path recipe.json

FROM rust:1.64.0-slim-bullseye AS cacher

RUN apt-get update && apt-get install --no-install-recommends -y libssl-dev=1.1.1n-0+deb11u3 pkg-config=0.29.2-1 && rm -rf /var/lib/apt/lists/*
RUN cargo install cargo-chef

WORKDIR /opt/directory-watcher/services/directory-watcher/

COPY --from=planner /opt/directory-watcher/services/directory-watcher/recipe.json recipe.json
COPY libraries/rust/platform/ /opt/directory-watcher/libraries/rust/platform
COPY libraries/rust/events/ /opt/directory-watcher/libraries/rust/events
COPY libraries/rust/rpc-support/ /opt/directory-watcher/libraries/rust/rpc-support
COPY tools/message-compiler/ /opt/directory-watcher/tools/message-compiler

RUN cargo chef cook --release --recipe-path recipe.json

FROM rust:1.64.0-slim-bullseye AS build

RUN apt-get update && apt-get install --no-install-recommends -y libssl-dev=1.1.1n-0+deb11u3 pkg-config=0.29.2-1 && rm -rf /var/lib/apt/lists/*

COPY . /opt/directory-watcher/
WORKDIR /opt/directory-watcher/services/directory-watcher/
COPY --from=cacher /opt/directory-watcher/services/directory-watcher/target target
COPY --from=cacher /usr/local/cargo /usr/local/cargo

RUN cargo build --release

FROM debian:bullseye-slim

RUN apt-get update && apt-get install --no-install-recommends -y libssl1.1=1.1.1n-0+deb11u3 && rm -rf /var/lib/apt/lists/*

COPY --from=build /opt/directory-watcher/services/directory-watcher/target/release/directory-watcher /opt/directory-watcher/
COPY services/directory-watcher/runtime.configuration.json /etc/ap/

# todo remove this once we actually mount something
RUN mkdir /tmp/a/ \
    && touch /tmp/a/a \
    && mkdir /tmp/a/b/ \
    && touch /tmp/a/b/c

ENTRYPOINT ["/opt/directory-watcher/directory-watcher"]
